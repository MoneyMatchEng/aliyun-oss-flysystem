<?php

namespace AlphaSnow\Flysystem\AliyunOss\Tests;

use AlphaSnow\Flysystem\AliyunOss\AliyunOssAdapter;
use League\Flysystem\Config;
use Mockery\MockInterface;
use OSS\Core\OssException;
use OSS\Model\ObjectInfo;
use OSS\Model\PrefixInfo;
use OSS\OssClient;
use PHPUnit\Framework\TestCase;

class AliyunOssAdapterTest extends TestCase
{
    public function aliyunProvider()
    {
        $accessId = getenv("ALIYUN_OSS_ACCESS_ID");
        $accessKey = getenv("ALIYUN_OSS_ACCESS_KEY");
        $bucket = getenv("ALIYUN_OSS_BUCKET");
        $endpoint = getenv("ALIYUN_OSS_ENDPOINT");

        /**
         * @var $client OssClient
         */
        $client = \Mockery::mock(OssClient::class, [$accessId,$accessKey,$endpoint])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $adapter = new AliyunOssAdapter($client, $bucket);

        return [
            [$adapter,$client]
        ];
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testWrite($adapter, $client)
    {
        $client->allows([
            "putObject" => ["oss-requestheaders" => ["Date" => "Thu, 10 Jun 2021 02:42:20 GMT","Content-Length" => "7","Content-Type" => "application/octet-stream"]]
        ]);

        $result = $adapter->write("foo/bar.md", "content", new Config());

        $this->assertSame([
            "type" => "file",
            "path" => "foo/bar.md",
            "timestamp" => 1623292940,
            "size" => 7,
            "mimetype" => "application/octet-stream",
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testWriteStream($adapter, $client)
    {
        $client->allows([
            "uploadStream" => ["info" => ["upload_content_length" => 7.0],"oss-requestheaders" => ["Date" => "Thu, 10 Jun 2021 02:42:20 GMT","Content-Type" => "application/octet-stream"]]
        ]);

        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "content");
        $result = $adapter->writeStream("foo/bar.md", $fp, new Config());
        fclose($fp);

        $this->assertSame([
            "type" => "file",
            "path" => "foo/bar.md",
            "timestamp" => 1623292940,
            "size" => 7,
            "mimetype" => "application/octet-stream",
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testUpdate($adapter, $client)
    {
        $client->shouldReceive("putObject")
            ->andReturn(
                ["oss-requestheaders" => ["Date" => "Thu, 10 Jun 2021 02:42:20 GMT","Content-Length" => "6","Content-Type" => "application/octet-stream"]]
            );

        $result = $adapter->update("foo/bar.md", "update", new Config());
        $this->assertSame([
            "type" => "file",
            "path" => "foo/bar.md",
            "timestamp" => 1623292940,
            "size" => 6,
            "mimetype" => "application/octet-stream",
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testUpdateStream($adapter, $client)
    {
        $client->allows([
            "uploadStream" => ["info" => ["upload_content_length" => 7.0],"oss-requestheaders" => ["Date" => "Thu, 10 Jun 2021 02:42:20 GMT","Content-Type" => "application/octet-stream"]]
        ]);

        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "content");
        $result = $adapter->updateStream("foo/bar.md", $fp, new Config());
        fclose($fp);

        $this->assertSame([
            "type" => "file",
            "path" => "foo/bar.md",
            "timestamp" => 1623292940,
            "size" => 7,
            "mimetype" => "application/octet-stream",
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testRename($adapter, $client)
    {
        $client->allows([
            "copyObject" => null,
            "deleteObject" => null
        ]);

        $result = $adapter->rename("foo/bar.md", "foo/baz.md");
        $this->assertTrue($result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testCopy($adapter, $client)
    {
        $client->allows([
            "copyObject" => null
        ]);

        $result = $adapter->copy("foo/bar.md", "foo/baz.md");
        $this->assertTrue($result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testDelete($adapter, $client)
    {
        $client->allows([
            "deleteObject" => null
        ]);

        $result = $adapter->delete("foo/bar.md");
        $this->assertTrue($result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testDeleteDir($adapter, $client)
    {
        $listObjects = \Mockery::mock("stdClass")->allows([
            "getObjectList" => [new ObjectInfo(
                "foo/bar.md",
                "Thu, 10 Jun 2021 02:42:20 GMT",
                "9A0364B9E99BB480DD25E1F0284C8555",
                "application/octet-stream",
                7,
                "standard"
            ),new ObjectInfo(
                "foo/baz.md",
                "Thu, 10 Jun 2021 02:42:20 GMT",
                "9A0364B9E99BB480DD25E1F0284C8555",
                "application/octet-stream",
                7,
                "standard"
            )],
            "getPrefixList" => [],
            "getNextMarker" => ""
        ]);
        $client->allows([
            "listObjects" => $listObjects,
            "deleteObjects" => null
        ]);


        $result = $adapter->deleteDir("foo/");
        $this->assertTrue($result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testCreateDir($adapter, $client)
    {
        $client->allows([
            "createObjectDir" => null
        ]);

        $result = $adapter->createDir("baz/", new Config());
        $this->assertSame([
            "type" => "dir",
            "path" => "baz/"
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testSetVisibility($adapter, $client)
    {
        $client->allows([
            "putObjectAcl" => null
        ]);

        $result = $adapter->setVisibility("foo/bar.md", "public");
        $this->assertSame([
            "visibility" => "public"
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testHas($adapter, $client)
    {
        $client->shouldReceive("doesObjectExist")
            ->andReturn(true, false)
            ->times(2);

        $this->assertTrue($adapter->has("foo/bar.md"));
        $this->assertFalse($adapter->has("foo/baz.md"));
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testRead($adapter, $client)
    {
        $client->shouldReceive("getObject")
            ->andReturn("content");

        $result = $adapter->read("foo/bar.md");
        $this->assertSame([
            "type" => "file",
            "path" => "foo/bar.md",
            "contents" => "content"
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testReadStream($adapter, $client)
    {
        $client->shouldReceive("getObject")
            ->andReturn(null);

        $result = $adapter->readStream("foo/bar.md");

        $this->assertTrue(is_resource($result['stream']));
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testListContents($adapter, $client)
    {
        $listObjects = \Mockery::mock("stdClass")->allows([
            "getObjectList" => [new ObjectInfo(
                "foo/bar.md",
                "Thu, 10 Jun 2021 02:42:20 GMT",
                "9A0364B9E99BB480DD25E1F0284C8555",
                "application/octet-stream",
                7,
                "standard"
            )],
            "getPrefixList" => [new PrefixInfo("foo/baz/")],
            "getNextMarker" => ""
        ]);
        $client->allows([
            "listObjects" => $listObjects
        ]);
        $file = ["timestamp" => strtotime("Thu, 10 Jun 2021 02:42:20 GMT")];

        $result = $adapter->listContents("foo/");
        $this->assertSame([
            [
                "type" => "file",
                "path" => "foo/bar.md",
                "size" => 7,
                "timestamp" => $file["timestamp"]
            ],
            [
                "type" => "dir",
                "path" => "foo/baz/",
                "size" => 0,
                "timestamp" => 0
            ]
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetMetadata($adapter, $client)
    {
        $client->shouldReceive("getObjectMeta")
            ->andReturn(["info" => ["download_content_length" => 7,"filetime" => 1623292940,"content_type" => "application/octet-stream"]]);

        $result = $adapter->getMetadata("foo/bar.md");
        $this->assertSame([
            "type" => "file",
            "path" => "foo/bar.md",
            "size" => 7,
            "timestamp" => 1623292940,
            "mimetype" => "application/octet-stream"
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetSize($adapter, $client)
    {
        $client->shouldReceive("getObjectMeta")
            ->andReturn(["info" => ["download_content_length" => 7,"filetime" => 1623292940,"content_type" => "application/octet-stream","url" => "http://my-storage.oss-cn-shanghai.aliyuncs.com/foo/bar.md"]]);

        $result = $adapter->getSize("foo/bar.md");
        $this->assertSame([
            "size" => 7,
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetMimetype($adapter, $client)
    {
        $client->shouldReceive("getObjectMeta")
            ->andReturn(["info" => ["download_content_length" => 7.0,"filetime" => 1623292940,"content_type" => "application/octet-stream","url" => "http://my-storage.oss-cn-shanghai.aliyuncs.com/foo/bar.md"]]);

        $result = $adapter->getMimetype("foo/bar.md");
        $this->assertSame([
            "mimetype" => "application/octet-stream",
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetTimestamp($adapter, $client)
    {
        $client->shouldReceive("getObjectMeta")
            ->andReturn(["info" => ["download_content_length" => 7.0,"filetime" => 1623292940,"content_type" => "application/octet-stream","url" => "http://my-storage.oss-cn-shanghai.aliyuncs.com/foo/bar.md"]]);

        $result = $adapter->getTimestamp("foo/bar.md");
        $this->assertSame([
            "timestamp" => 1623292940,
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetVisibility($adapter, $client)
    {
        $client->shouldReceive("getObjectAcl")
            ->andReturn("public-read");

        $result = $adapter->getVisibility("foo/bar.md");
        $this->assertSame([
            "visibility" => "public",
        ], $result);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetClient($adapter, $client)
    {
        $adapterClient = $adapter->getClient();

        $this->assertSame($client, $adapterClient);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     */
    public function testGetBucket($adapter)
    {
        $bucket = $adapter->getBucket();

        $this->assertSame(getenv("ALIYUN_OSS_BUCKET"), $bucket);
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     */
    public function testOptions($adapter)
    {
        $options = [
            "Content-Type" => "application/octet-stream"
        ];
        $adapter->setOptions($options);

        $this->assertSame($options, $adapter->getOptions());
    }

    /**
     * @dataProvider aliyunProvider
     *
     * @param AliyunOssAdapter $adapter
     * @param OssClient|MockInterface $client
     */
    public function testGetException($adapter,$client)
    {
        $errorException = new OssException('error');
        $client->shouldReceive("getObject")
            ->andThrow($errorException);

        $result = $adapter->read('none.md');
        $exception = $adapter->getException();

        $this->assertFalse($result);
        $this->assertSame($errorException,$exception);
    }

    public function testCreate()
    {
        $accessId = getenv("ALIYUN_OSS_ACCESS_ID");
        $accessKey = getenv("ALIYUN_OSS_ACCESS_KEY");
        $bucket = getenv("ALIYUN_OSS_BUCKET");
        $endpoint = getenv("ALIYUN_OSS_ENDPOINT");

        $adapter = AliyunOssAdapter::create($accessId,$accessKey,$bucket,$endpoint);
        $this->assertInstanceOf(AliyunOssAdapter::class,$adapter);
    }
}
