<?php
if (!defined('EXECUTION_ALLOWED')) {
    define('EXECUTION_ALLOWED', true); // Temporarily allow for config setup or adjust as per project standard
}

// Cloudinary Configuration
define('CLOUDINARY_CLOUD_NAME', defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : 'dkrv5ryn3'); 
define('CLOUDINARY_API_KEY', defined('CLOUDINARY_API_KEY') ? CLOUDINARY_API_KEY : '585677224659683');
define('CLOUDINARY_API_SECRET', defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : 'Inl9Ge31k0gOKu57JMCA2UB_54s');

// Folder structure
define('CLD_FOLDER_USERS', 'users/profile/');
define('CLD_FOLDER_WORKER_PROFILE', 'workers/profile/');
define('CLD_FOLDER_WORKER_DOCS', 'workers/documents/');
define('CLD_FOLDER_WORKER_PREV_WORK', 'workers/previous_work/');
define('CLD_FOLDER_WORKER_WORK_DONE', 'workers/work_done/');
?>
