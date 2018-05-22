<?php

namespace MyBuilder\Library\WhenIWork\Tests\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use Mockery;
use MyBuilder\Library\WhenIWork\Service\WhenIWorkApi;
use GuzzleHttp\Psr7\Stream;

/**
 * @group unit
 * @coversDefaultClass \MyBuilder\Library\WhenIWork\Service\WhenIWorkApi
 */
class WhenIWorkApiTest extends \PHPUnit_Framework_TestCase
{
    private const DEVELOPER_KEY = '12345';
    private const USERNAME  = 'test@test.test';
    private const PASSWORD = 'heavypass';
    private const TOKEN = 'someHiddenToken';

    /**
     * @var WhenIWorkApi
     */
    private $whenIWorkApi;

    /**
     * @var Client|\Mockery\MockInterface
     */
    private $guzzleClient;

    /**
     * @var Request|\Mockery\MockInterface
     */
    private $request;

    public function setUp()
    {
        $this->request = Mockery::mock('GuzzleHttp\Psr7\Message\Request');
        $this->guzzleClient = Mockery::mock(Client::class);

        $this->whenIWorkApi = new WhenIWorkApi(
            $this->guzzleClient,
            self::DEVELOPER_KEY,
            self::USERNAME,
            self::PASSWORD
        );
    }

    /**
     * @expectedException \MyBuilder\Library\WhenIWork\Exception\WhenIWorkApiException
     */
    public function test_setup_and_exception_from_guzzle_client(): void
    {
        $this->guzzleClientShouldSetupToken();

        $this->guzzleClient->shouldReceive('get')
            ->with(
                WhenIWorkApi::WHEN_I_WORK_ENDPOINT . '/users',
                array('headers' => array('W-Token' => self::TOKEN))
            )
            ->once()
            ->andThrow(new BadResponseException('', new Request('GET', 'foo')));

        $this->whenIWorkApi->usersListingUsers();
    }

    private function guzzleClientShouldSetupToken(): void
    {
        $this->guzzleClient
            ->shouldReceive('post')
            ->with(
                WhenIWorkApi::WHEN_I_WORK_ENDPOINT . '/login',
                array(
                    'headers' => array('W-Key' => self::DEVELOPER_KEY),
                    'json' => array('username' => self::USERNAME, 'password' => self::PASSWORD)
                )
            )
            ->once()
            ->andReturn($this->request);

        $mockStream = Mockery::mock(Stream::class);
        $mockStream->shouldReceive('close')->once();
        $mockStream->shouldReceive('getContents')->once()->andReturn('{"login":{"token":"someHiddenToken"}}');

        $this->request->shouldReceive('getBody')->once()->andReturn($mockStream);
        $this->request->shouldReceive('\GuzzleHttp\json_decode')->andReturn(array('login' => array('token' => self::TOKEN)));
    }

    /**
     * Are the dates in the URLs being created OK?
     *
     * @covers ::parseDateTimeToApiFormat()
     */
    public function test_ParseDates()
    {
        $whenIWorkApiTest = new class($this->guzzleClient, '', '', '') extends WhenIWorkApi {
            public $urlVisited;

            protected function fetchResourceForKey($entryPoint, $valueKey)
            {
                $this->urlVisited = $entryPoint;

                return [];
            }
        };

        $startDate = new \DateTime('2018-01-01 12:34');
        $endDate = new \DateTime('2018-01-31 23:45');

        $whenIWorkApiTest->timesListingTimes($startDate, $endDate);
        $this->assertSame('/times/?start=2018-01-01&end=2018-01-31', $whenIWorkApiTest->urlVisited);

        $whenIWorkApiTest->payrollListingPayrolls($startDate, $endDate);
        $this->assertSame('/payrolls/?start=2018-01-01&end=2018-01-31', $whenIWorkApiTest->urlVisited);

        $whenIWorkApiTest->timesGetUserTimes(123, $startDate, $endDate);
        $this->assertSame('/times/user/123?start=2018-01-01&end=2018-01-31', $whenIWorkApiTest->urlVisited);
    }
}
