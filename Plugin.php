<?php
/**
 * 将 Typecho 的附件上传至七牛云存储中。
 * 
 * @package Qiniu File
 * @author lscho
 * @version 0.1
 * @link https://lscho.com/
 * @date 2017-04-01
 */

// 初始化 SDK
require_once __DIR__ . '/sdk/autoload.php';
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;


class QiniuFile_Plugin implements Typecho_Plugin_Interface{
    // 激活插件
    public static function activate(){
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('QiniuFile_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('QiniuFile_Plugin', 'modifyHandle');
        Typecho_Plugin::factory('Widget_Upload')->deleteHandle = array('QiniuFile_Plugin', 'deleteHandle');
        Typecho_Plugin::factory('Widget_Upload')->attachmentHandle = array('QiniuFile_Plugin', 'attachmentHandle');
        return _t('插件已经激活，需先配置七牛的信息！');
    }

    
    // 禁用插件
    public static function deactivate(){
        return _t('插件已被禁用');
    }

    
    // 插件配置面板
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $bucket = new Typecho_Widget_Helper_Form_Element_Text('bucket', null, null, _t('空间名称'));
        $form->addInput($bucket->addRule('required', _t('“空间名称”不能为空！')));

        $accesskey = new Typecho_Widget_Helper_Form_Element_Text('accesskey', null, null, _t('AccessKey'));
        $form->addInput($accesskey->addRule('required', _t('AccessKey 不能为空！')));

        $secretkey = new Typecho_Widget_Helper_Form_Element_Text('secretkey', null, null, _t('SecretKey'));
        $form->addInput($secretkey->addRule('required', _t('SecretKey 不能为空！')));

        $domain = new Typecho_Widget_Helper_Form_Element_Text('domain', null, 'http://', _t('绑定域名'), _t('以 http:// 开头，结尾不要加 / ！'));
        $form->addInput($domain->addRule('required', _t('请填写空间绑定的域名！'))->addRule('url', _t('您输入的域名格式错误！')));

        $savepath = new Typecho_Widget_Helper_Form_Element_Text('savepath', null, '{year}/{month}/', _t('保存路径格式'), _t('附件保存路径的格式，默认为 Typecho 的 {year}/{month}/ 格式，注意<strong style="color:#C33;">前面不要加 / </strong>！<br />可选参数：{year} 年份、{month} 月份、{day} 日期'));
        $form->addInput($savepath->addRule('required', _t('请填写保存路径格式！')));

        $list = array('关闭', '开启');
        $element = new Typecho_Widget_Helper_Form_Element_Radio('is_save', $list, 0, _t('是否在本服务器保留备份'),_t('开启后会先上传至服务器一份，然后再同步到七牛，如果同步七牛失败则使用服务器地址'));
        $form->addInput($element);        
    }


    // 个人用户配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form){
    }


    // 获得插件配置信息
    public static function getConfig(){
        return Typecho_Widget::widget('Widget_Options')->plugin('QiniuFile');
    }


    public static function deleteFile($filepath){
        // 获取插件配置
        $option = self::getConfig();

        $auth = new Auth($option->accesskey, $option->secretkey);
        $bucketManager = new BucketManager($auth);

        // 删除
        return $bucketManager->delete($option->bucket, $filepath);
    }


    // 上传文件
    public static function uploadFile($file, $content = null){
        // 获取上传文件
        if (empty($file['name'])) return false;

        // 校验扩展名
        $part = explode('.', $file['name']);
        $ext = (($length = count($part)) > 1) ? strtolower($part[$length-1]) : '';
        if (!Widget_Upload::checkFileType($ext)) return false;

        // 获取插件配置
        $option = self::getConfig();
        $date = new Typecho_Date(Typecho_Widget::widget('Widget_Options')->gmtTime);

        // 保存位置
        $savepath = preg_replace(array('/\{year\}/', '/\{month\}/', '/\{day\}/'), array($date->year, $date->month, $date->day), $option->savepath);
        $_name=sprintf('%u', crc32(uniqid())) . '.' . $ext;
        $savename = $savepath . $_name;
        if (isset($content)){
            $savename = $content['attachment']->path;
            self::deleteFile($savename);
        }
        // 上传文件
        $filename = $file['tmp_name'];
        if (!isset($filename)) return false;

        //是否保存在本地
        if($option->is_save){
            $options = Typecho_Widget::widget('Widget_Options');
            $date = new Typecho_Date($options->gmtTime);
            $path = __TYPECHO_ROOT_DIR__. '/usr/uploads/' . $date->year . '/' . $date->month.'/';
            if(!file_exists($path)){
                mkdir($path,0777,true);
            }
            if(move_uploaded_file($filename,$path.$_name)){
                $data=array(
                    'name'  =>  $file['name'],
                    'path'  =>  $path.$_name,
                    'size'  =>  $file['size'],
                    'type'  =>  $ext,
                    'mime'  =>  Typecho_Common::mimeContentType($_name)                    
                );
                $filename=$path.$_name;
            }
        }
        // 七牛上传凭证
        $auth = new Auth($option->accesskey, $option->secretkey);
        $token = $auth->uploadToken($option->bucket);
        $uploadManager = new UploadManager();
        // 上传
        list($result, $error) = $uploadManager->putFile($token, $savename, $filename);
        if ($error == null){
            return array(
                'name'  =>  $file['name'],
                'path'  =>  $savename,
                'size'  =>  $file['size'],
                'type'  =>  $ext,
                'mime'  =>  Typecho_Common::mimeContentType($savename)
            );
        }else{
            return $data?$data:false;
        }
    }


    // 上传文件处理函数
    public static function uploadHandle($file){
        return self::uploadFile($file);
    }


    // 修改文件处理函数
    public static function modifyHandle($content, $file){
        return self::uploadFile($file, $content);
    }


    // 删除文件
    public static function deleteHandle(array $content){
        self::deleteFile($content['attachment']->path);
    }


    // 获取实际文件绝对访问路径
    public static function attachmentHandle(array $content){
        $option = self::getConfig();
        return Typecho_Common::url($content['attachment']->path, $option->domain);
    }
}
