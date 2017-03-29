<?php

namespace Findologic\PlentymarketsTest\Wrapper;

use Findologic\Plentymarkets\Wrapper\Csv;
use PHPUnit_Framework_TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;

class CsvTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fileSystemMock;

    /**
     * @var \org\bovigo\vfs\vfsStreamFile
     */
    protected $fileMock;

    public function setUp()
    {
        $this->fileSystemMock = vfsStream::setup();
        $this->fileMock = new vfsStreamFile('Export.csv');
        $this->fileSystemMock->addChild($this->fileMock);
    }

    /**
     * Check if file was created successfully
     */
    public function testCreateFile()
    {
        $url = $this->getMockedFileSystemPath('Export.csv');
        $wrapperMock = $this->getWrapperMock($url, array('convertData'));
        $wrapperMock->expects($this->once())->method('convertData')->willReturn(array());

        $wrapperMock->wrapItem($this->getItemDataMock());

        $this->assertTrue(is_file($url));
        $this->assertTrue(file_exists($url));
    }

    public function testCreateFileException()
    {
        $url = $this->getMockedFileSystemPath('Export.csv');
        $wrapperMock = $this->getWrapperMock($url, array('getResults'));
        //TODO: mocking to throw exception
        $this->fileMock->chmod(0);
        $this->setExpectedException('\Findologic\Plentymarkets\Exception\CriticalException');
        $wrapperMock->wrapItem($this->getItemDataMock());
    }

    /**
     * Check if data is converted
     */
    public function testConvertData()
    {
        $wrapperMock = $this->getWrapperMock('Test', array('getStream'));

        $data = $wrapperMock->convertData($this->getItemDataMock());

        $this->assertSame("1|2", $data['ordernumber']);
        $this->assertSame("Description", $data['description']);
        $this->assertSame("Test%5B0%5D=Value+1&Test%5B1%5D=Value+2", $data['attributes']);
    }

    /**
     * @param string $path
     * @return string
     */
    protected function getMockedFileSystemPath($path)
    {
        return $this->fileSystemMock->url() . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * @param string $url
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getWrapperMock($url, $methods)
    {
        $wrapperMock = $this->getMockBuilder('\Findologic\Plentymarkets\Wrapper\Csv')
            ->setConstructorArgs(array($url))
            ->setMethods($methods)
            ->getMock();

        return $wrapperMock;
    }

    /**
     * @param array $customData
     * @return array
     */
    protected function getItemDataMock($customData = array())
    {
        $data = array(
            'id' => 1,
            'ordernumber' => array('1', '2'),
            'name' => 'Name',
            'summary' => 'Summary',
            'description' => '<p>Description</p>',
            'price' => 10,
            'instead' => 8,
            'maxprice' => 12,
            'taxrate' => '19.00',
            'url' => 'https://test.com/path/item.html',
            'image' => 'https://test.com/path/image.jpg',
            'base_unit' => 'Unit',
            'package_size' => 0.75,
            'price_id' => 12,
            'attributes' => array('Test' => array('Value 1', 'Value 2')),
            'keywords' => 'test, keyword, etc',
            'groups' => 'Group 1',
            'bonus' => '1',
            'sales_frequency' => 1,
            'date_added' => 'date',
            'sort' => 1,
        );

        $data = array_merge($data, $customData);

        return $data;
    }
}