<?php

namespace Espier\Qiniu;

use League\Flysystem\Adapter\AbstractAdapter as AbstractAdapter;
use League\Flysystem\Config;

use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Adapter\Polyfill\StreamedReadingTrait;
use League\Flysystem\Adapter\Polyfill\StreamedWritingTrait;

use Qiniu\Auth;
use Qiniu\Http\Error;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

class Adapter extends AbstractAdapter {

    use StreamedReadingTrait, StreamedWritingTrait, NotSupportingVisibilityTrait;

    private $buckets = [];
    private $domain = null;

    private $uploadBucket = null;//上传到的Bucket
    private $toBucket = null;//移动到指定Bucket

    private $auth = null;

    private $bucketManager = null;

    private $uploadManager = null;

    public function __construct($accessKey, $secretKey, $buckets)
    {
        $this->getAuth($accessKey, $secretKey);
        $this->getBucketManager();

        $this->buckets = $buckets;
        $this->withBucket();
    }

    //指定Bucket
    //如果未指定则默认为第一个Bucket
    public function withBucket($bucketName=null)
    {
        if( empty($this->buckets) )
        {
            throw new Exception('请正确配置七牛存储服务参数');
        }

        if( $bucketName )
        {
            $this->uploadBucket = $this->buckets[$bucketName]['name'];
            $this->setPathPrefix($this->buckets[$bucketName]['domain']);
        }
        else
        {
            $currentBuckets = current($this->buckets);

            $this->uploadBucket = $currentBuckets['name'];
            $this->setPathPrefix($currentBuckets['domain']);
        }

        return $this;
    }

    /**
     * 移动到指定Bucket
     *
     * @param string $bucketName
     */
    public function moveToBucket($bucketName)
    {
        if( isset($this->buckets[$bucketName]) )
        {
            $this->toBucket = $this->buckets[$bucketName]['name'];
        }
        return $this;
    }

    private function getAuth($accessKey, $secretKey)
    {
        if( $this->auth == null )
        {
            $this->auth = new Auth($accessKey, $secretKey);
        }

        return $this->auth;
    }

    protected function getBucketManager()
    {
        if ($this->bucketManager == null)
        {
            $this->bucketManager = new BucketManager($this->auth);
        }

        return $this->bucketManager;
    }

    private function getUploadManager()
    {
        if ($this->uploadManager == null)
        {
            $this->uploadManager = new UploadManager();
        }
        return $this->uploadManager;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        if($bucket = $config->get('bucket', null)) {
            $this->withBucket($bucket);
        }

        $token = $this->auth->uploadToken($this->uploadBucket, $path, 3600, null, true);

        $params            = $config->get('params', null);
        $mime              = $config->get('mime', 'application/octet-stream');
        $checkCrc          = $config->get('checkCrc', false);
        $uploadManager    = $this->getUploadManager();
        list($result, $error) = $uploadManager->put($token, $path, $contents, $params, $mime, $checkCrc);

        return ( $error !== null ) ? false : $result;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $toBucket =  $this->toBucket ? $this->toBucket : $this->uploadBucket;

        list($result, $error) = $this->bucketManager->move($this->uploadBucket, $path, $toBucket, $newpath);

        return ($result !== null) ? false : true;
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $toBucket =  $this->toBucket ? $this->toBucket : $this->uploadBucket;

        list($result, $error) = $this->bucketManager->copy($this->uploadBucket, $path, $this->toBucket, $newpath);

        return ($result !== null) ? false : true;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $result = $this->bucketManager->delete($this->uploadBucket, $path);

        return ($result !== null) ? false : true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $files = $this->listContents($dirname);

        foreach ($files as $file)
        {
            $this->delete($file['path']);
        }

        return true;
    }
    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        return ['path' => $dirname];
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        return $this->getMetadata($path) ? true : false;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $url = $this->getUrl($path);
        return array('contents' => file_get_contents($url));
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        list($items, $marker, $error) = $this->bucketManager->listFiles($this->uploadBucket, $directory);
        if( $error !== null || empty($items) )
        {
            return array();
        }

        $contents = array();
        foreach ($items as $file )
        {
            $item = [
                'type'      => $file['mimeType'],
                'path'      => $file['key'],
                'timestamp' => $file['putTime'],
                'size'      => $file['fsize']
            ];

            $contents[] = $item;
        }
        return $contents;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        $result = $this->bucketManager->stat($this->uploadBucket, $path);

        return ($result[1] !== null ) ? false : $result[0];
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadataWithChildren('fsize', 'size');
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadataWithChildren('mimeType', 'mimetype');
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadataWithChildren('putTime', 'timestamp');
    }

    private function getMetadataWithChildren($field, $newField)
    {
        $stat = $this->getMetadata($path);
        return $stat ? [ $newField => $stat[$field] ] : false;
    }

    //获取URL地址
    public function getUrl($path)
    {
        $baseUrl = $this->applyPathPrefix($path);
        return $this->auth->privateDownloadUrl($baseUrl);
    }
}

