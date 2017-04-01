## QiniuFile_For_Typecho

---

Qiniu File 是一款 Typecho 的七牛云存储插件，可将 Typecho 的文件功能接入到七牛云存储中，包括上传附件、修改附件、删除附件，以及获取文件在七牛的绝对网址。文件目录结构默认与 Typecho 的 `/year/month/` 保持一致，也可自定义配置，方便迁移。

#### 使用方法：

第一步：下载本插件，放在 `usr/plugins/` 目录中；  
第二步：激活插件；  
第三步：填写空间名称、Access Key、Secret Key、域名 等配置；  
第四步：完成。


####　说明

开启在本服务备份，会在上传时在本服务器备份一份后上传至七牛，上传在本地服务器的路径按照 Typecho 的配置，上传至七牛的文件则按照插件的配置。