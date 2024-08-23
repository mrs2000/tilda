<?php

namespace tests;

use mrssoft\tilda\TildaApi;

final class TildaApiTest extends \PHPUnit\Framework\TestCase
{
    protected array $params = [];

    protected TildaApi $api;

    private function loadParams(): array
    {
        return json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'params.json'), true);
    }

    protected function setUp(): void
    {
        $this->params = $this->loadParams();
        $this->api = new TildaApi($this->params);
        $this->api->cache = false;
    }

    public function testLoadPageHtml()
    {
        $html = $this->api->pageHtml(null, 17207520);

        self::assertNotEmpty($html);
    }
}