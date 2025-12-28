<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/cloudinary.php';

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;

/**
 * Cloudinary Helper Class
 */
class CloudinaryHelper {
    private static $instance = null;
    private $cloudinary;

    private function __construct() {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => CLOUDINARY_CLOUD_NAME,
                'api_key'    => CLOUDINARY_API_KEY,
                'api_secret' => CLOUDINARY_API_SECRET,
            ],
        ]);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Upload an image to Cloudinary with specific transformations
     * 
     * @param string $file_path Local path to the file
     * @param string $folder Cloudinary folder
     * @param string $type 'standard' or 'high-res'
     * @return array [url, public_id]
     */
    public function uploadImage($file_path, $folder, $type = 'standard') {
    try {
        // Target ~350KB with controlled resolution and aggressive compression
        $transformations = [
            'crop'          => 'limit',
            'fetch_format'  => 'auto',
            'height'        => 1024,
            'width'         => 1024,
            'quality'       => 'auto:eco'
        ];

        $options = [
            'folder'                => $folder,
            'transformation'        => $transformations,
            'resource_type'         => 'image',
            'limit_execution_time'  => 120
        ];

        $result = $this->cloudinary
            ->uploadApi()
            ->upload($file_path, $options);

        return [
            'url'       => $result['secure_url'],
            'public_id' => $result['public_id']
        ];

    } catch (Exception $e) {
        error_log("Cloudinary Upload Critical Error: " . $e->getMessage());
        if (isset($_SESSION)) {
            $_SESSION['last_cloudinary_error'] = $e->getMessage();
        }
        return null;
    }
}


    /**
     * Get a transformed URL for a public_id
     * 
     * @param string $public_id
     * @param array $transformations e.g. ['width' => 300, 'height' => 300, 'crop' => 'fill']
     * @return string
     */
    public function getUrl($public_id, $transformations = []) {
        if (!$public_id) return null;
        
        // Default quality and format
        $default_transforms = [
            'fetch_format' => 'auto',
            'quality' => 'auto'
        ];
        
        $final_transforms = array_merge($default_transforms, $transformations);
        
        // If it's already a full URL (legacy or migrated), we might need to handle it
        if (filter_var($public_id, FILTER_VALIDATE_URL)) {
            // For full URLs, we can't easily add transformations without parsing.
            // For now, return as is.
            return $public_id;
        }

        return $this->cloudinary->image($public_id)->addTransformation($final_transforms)->toUrl();
    }

    /**
     * Generate a responsive <img> tag with srcset
     * 
     * @param string $public_id
     * @param string $alt
     * @param string $class
     * @return string
     */
    public function getResponsiveImageTag($public_id, $alt = '', $class = '') {
        if (!$public_id) return '';

        // If it's a legacy URL, return a standard tag
        if (filter_var($public_id, FILTER_VALIDATE_URL)) {
            return "<img src=\"$public_id\" alt=\"$alt\" class=\"$class\">";
        }

        // Generate different sizes
        $sizes = [400, 800, 1200, 1600];
        $srcset = [];
        
        foreach ($sizes as $size) {
            $url = $this->getUrl($public_id, ['width' => $size, 'crop' => 'limit']);
            $srcset[] = "$url {$size}w";
        }

        $default_url = $this->getUrl($public_id, ['width' => 800, 'crop' => 'limit']);
        
        return sprintf(
            '<img src="%s" srcset="%s" sizes="(max-width: 600px) 400px, (max-width: 1200px) 800px, 1200px" alt="%s" class="%s" loading="lazy">',
            $default_url,
            implode(', ', $srcset),
            htmlspecialchars($alt),
            htmlspecialchars($class)
        );
    }

    /**
     * Delete an image from Cloudinary
     * 
     * @param string $public_id
     * @return bool
     */
    public function deleteImage($public_id) {
        if (!$public_id) return true;
        try {
            $this->cloudinary->uploadApi()->destroy($public_id);
            return true;
        } catch (Exception $e) {
            error_log("Cloudinary Delete Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
