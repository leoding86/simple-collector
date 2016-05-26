<?php
namespace leoding86\SimpleCollector;

class CollectorPictureMaker
{
    private $rootUrl;
    private $savePath;
    private $maxSize;
    private $picturesInfo;
    private $pictureResizes;

    public function __construct()
    {
        $this->rootUrl = null;
        $this->maxSize = ['width' => 4096, 'height' => 2160];
    }

    final static private function parsePath($path)
    {   
        if (substr($path, -1) === '/') {
            return substr($path, 0, -1);
        }
        return $path;
    }

    /**
     * 生成图片文件名
     * @param  string $url 图片地址
     * @return string
     */
    final private function generateName($url)
    {
        $ext = substr($url, strrpos($url, '.') + 1);
        if (preg_match('/jpg|jpeg|gif|png|bmp/', $ext)) {
            return md5($url . microtime()) . '.' . $ext;
        }
        else {
            return '';
        }
    }

    /**
     * 获得图片数据
     * @param  string $path 图片路径
     * @return string
     */
    final private function getPictureData($path)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $path);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        list($result, $errno, $error) = [curl_exec($ch), curl_errno($ch), curl_error($ch)];
        curl_close($ch);

        if ($errno > 0) {
            return null;
        }

        return $result;
    }

    /**
     * 保存图片数据到指定路径
     * @param  string $picture_path 图片保存路径
     * @param  string $picture_data 图片数据
     * @return void
     */
    final private function savePicture($picture_path, $picture_data)
    {
        $fh = fopen($picture_path, 'wb');
        fwrite($fh, $picture_data);
        fclose($fh);
    }

    /**
     * 获得imagecopyresampled需要使用的参数
     * @param  [type]  $orig_w [description]
     * @param  [type]  $orig_h [description]
     * @param  [type]  $dest_w [description]
     * @param  [type]  $dest_h [description]
     * @param  boolean $crop   [description]
     * @return [type]          [description]
     */
    final private function getPictureResizeDimensions($orig_w, $orig_h, $dest_w, $dest_h, $crop = false)
    {
        if ($orig_w <= 0 || $orig_h <= 0)
            return false;
        // at least one of dest_w or dest_h must be specific
        if ($dest_w <= 0 && $dest_h <= 0)
            return false;

        if ( $crop ) {
            // crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
            $aspect_ratio = $orig_w / $orig_h;
            $new_w = min($dest_w, $orig_w);
            $new_h = min($dest_h, $orig_h);

            if ( ! $new_w ) {
                $new_w = (int) round( $new_h * $aspect_ratio );
            }

            if ( ! $new_h ) {
                $new_h = (int) round( $new_w / $aspect_ratio );
            }

            $size_ratio = max($new_w / $orig_w, $new_h / $orig_h);

            $crop_w = round($new_w / $size_ratio);
            $crop_h = round($new_h / $size_ratio);

            if ( ! is_array( $crop ) || count( $crop ) !== 2 ) {
                $crop = array( 'center', 'center' );
            }

            list( $x, $y ) = $crop;

            if ( 'left' === $x ) {
                $s_x = 0;
            } elseif ( 'right' === $x ) {
                $s_x = $orig_w - $crop_w;
            } else {
                $s_x = floor( ( $orig_w - $crop_w ) / 2 );
            }

            if ( 'top' === $y ) {
                $s_y = 0;
            } elseif ( 'bottom' === $y ) {
                $s_y = $orig_h - $crop_h;
            } else {
                $s_y = floor( ( $orig_h - $crop_h ) / 2 );
            }
        } else {
            // don't crop, just resize using $dest_w x $dest_h as a maximum bounding box
            $crop_w = $orig_w;
            $crop_h = $orig_h;

            $s_x = 0;
            $s_y = 0;

            list( $new_w, $new_h ) = [$dest_w, $dest_h];
        }

        // if the resulting image would be the same size or larger we don't want to resize it
        if ( $new_w >= $orig_w && $new_h >= $orig_h && $dest_w != $orig_w && $dest_h != $orig_h ) {
            return false;
        }

        // the return array matches the parameters to imagecopyresampled()
        // int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
        return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );

        
    }

    /**
     * 开始下载图片
     * @return void
     */
    final public function download()
    {
        foreach ($this->picturesInfo as $name => $info) {
            $pic_data = $this->getPictureData($info['url']);
            if ($pic_data) {
                /* 保存原始图片 */
                $this->savePicture($this->savePath . '/' . $name, $pic_data);

                list($filename, $extension) = explode('.', $name);

                foreach ($this->pictureSizes as $size) {
                    $save_name = $this->savePath . '/'
                               . $filename . '_' . $size['width'] . 'x' . $size['height']
                               . '.' . $extension;

                    /* 获得剪切参数 */
                    $arguments = $this->getPictureResizeDimensions($info['width'], $info['height'], $size['width'], $size['height'], 'center');

                    /* 剪切后的图片 */
                    $orig_picture = imagecreatefromstring($pic_data);
                    $dest_picture = imagecreatetruecolor($size['width'], $size['height']);
                    list($dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) = array_pad($arguments, 8, 0);
                    imagecopyresampled($dest_picture, $orig_picture, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

                    /* 创建保存文件 */
                    touch($save_name);

                    /* 保存图片 */
                    switch ($extension) {
                        case 'jpg':
                        case 'jpeg':
                            imagejpeg($dest_picture, $save_name);
                            break;
                        case 'gif':
                            imagegif($dest_picture, $save_name);
                            break;
                        case 'bmp':
                            imagewbmp($dest_picture, $save_name);
                            break;
                        case 'png':
                            imagepng($dest_picture, $save_name);
                            break;
                        default:
                            # code...
                            break;
                    }
                    imagedestroy($orig_picture);
                    imagedestroy($dest_picture);
                }
            }
        }
    }

    final public function setRootUrl($url)
    {
        $this->rootUrl = self::parsePath($url);
    }

    final public function setSavePath($path)
    {
        $this->savePath = self::parsePath($path);
    }

    final public function setMaxSize($width, $height)
    {
        $this->maxSize = ['width' => $width, 'height' => $height];
    }

    final public function addSize($width, $height, $crop = array('center', 'center'))
    {
        if ($width > 0 && $height > 0) {
            $this->pictureSizes[] = [
                'width' => $width,
                'height'=> $height,
                'crop'  => $crop
            ];
        }
    }

    public function getUrl($url)
    {
        list($width, $height) = array_pad(getimagesize($url), 2, 0);

        if ($width <= $this->maxSize['width'] && $height <= $this->maxSize['height']) {
            $picture_name = $this->generateName($url);
            if ($picture_name) {
                $this->picturesInfo[$picture_name] = ['url' => $url, 'width' => $width, 'height' => $height];
                return $this->rootUrl . '/' . $picture_name;
            }
            else {
                return '';
            }
        }

        return '';
    }
}