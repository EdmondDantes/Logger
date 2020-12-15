<?PHP
namespace IfCastle\Logger\FS;

use Exceptions\SystemExceptionI;
use Exceptions\RuntimeExceptionI;

use Exceptions\Registry;

use Exceptions\LoggableException;
use Exceptions\LogicalException;
use Exceptions\MethodNotCallable;
use Exceptions\ObjectNotInitialized;
use Exceptions\UnexpectedMethodMode;
use Exceptions\RecursionLimitExceeded;
use Exceptions\RequiredValueEmpty;
use Exceptions\UnexpectedValueType;
use Exceptions\SystemException;
use Samples\RuntimeException;

/**
 * Test class for Writer.
 * Generated by PHPUnit on 2012-02-15 at 16:33:15.
 */
class ErrorsWriterTest               extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ErrorsWriter
     */
    protected $writer;

    protected $error_log;
    protected $system_log;
    protected $runtime_log;
    protected $dir;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $dir = UNIT_TEST_ROOT.'/../temp';
        if(!is_dir($dir))
        {
            mkdir($dir, 0777);
        }
        if(!is_dir($dir) || !is_writeable($dir))
        {
            throw new \Exception('Test temp dir not exists or not writable: '.$dir);
        }
        $dir .= '/test';
        if(!is_dir($dir))
        {
            mkdir($dir, 0777);
        }
        if(!is_dir($dir) || !is_writeable($dir))
        {
            throw new \Exception('Test temp dir not exists or not writable: '.$dir);
        }

        $this->error_log        = $dir.'/error.log_write';
        $this->system_log       = $dir.'/system.log_write';
        $this->runtime_log      = $dir.'/runtime.log_write';
        $this->dir              = $dir;

        $this->writer           = new ErrorsWriter
        (
            [
                ErrorsWriter::REMOTE_ADDRESS    => '192.168.1.1'
            ],
            [
                ErrorsWriter::TYPE              => Writer::FILE,
                ErrorsWriter::DESTINATION       => $this->error_log,
                ErrorsWriter::ROTATION          => true,
                ErrorsWriter::MAXSIZE           => 100,
                ErrorsWriter::MAX_COUNT         => 2
            ],
            [
                ErrorsWriter::TYPE              => Writer::FILE,
                ErrorsWriter::DESTINATION       => $this->system_log,
                ErrorsWriter::ROTATION          => true,
                ErrorsWriter::MAXSIZE           => 100,
                ErrorsWriter::MAX_COUNT         => 2
            ],
            [
                ErrorsWriter::TYPE              => Writer::FILE,
                ErrorsWriter::DESTINATION       => $this->runtime_log,
                ErrorsWriter::ROTATION          => true,
                ErrorsWriter::MAXSIZE           => 100,
                ErrorsWriter::MAX_COUNT         => 2
            ]
        );
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        Registry::reset_exception_log();
    }

    /**
     * Базовое тестирование записи исключений в журнал.
     * В этом тесте проверется целостность отправленных
     * и полученных данных.
     */
    public function testLog_exceptions()
    {
        foreach(glob($this->dir.'/*.log_write', GLOB_NOSORT) as $file)
        {
            unlink($file);
        }

        $this->assertFalse
        (
            is_file($this->error_log),
            'initial conditions: file '.$this->error_log.' must not exists'
        );
        $this->assertFalse
        (
            is_file($this->system_log),
            'initial conditions: file '.$this->system_log.' must not exists'
        );
        $this->assertFalse
        (
            is_file($this->runtime_log),
            'initial conditions: file '.$this->runtime_log.' must not exists'
        );

        /* @var $exceptions \Exceptions\BaseExceptionI[] */
        $exceptions     = [];

        $exceptions[]   = new LoggableException('test message 2', 10);
        $exceptions[]   = new LogicalException('logical message');
        $exceptions[]   = new MethodNotCallable('test_method');
        $exceptions[]   = new ObjectNotInitialized($this);
        $exceptions[]   = new UnexpectedMethodMode(__METHOD__, 'test_mode');
        $exceptions[]   = new RecursionLimitExceeded(10);
        $exceptions[]   = new RequiredValueEmpty('var', 'var_expected');
        $exceptions[]   = new UnexpectedValueType('var', '112233', '11');
        $exceptions[]   = new SystemException('system exceptions message1');
        $exceptions[]   = new SystemException('system exceptions message2');
        $exceptions[]   = new SystemException('system exceptions message3');
        $exceptions[]   = new RuntimeException('runtime');
        $exceptions[]   = new RuntimeException('runtime1');
        $exceptions[]   = new RuntimeException('runtime2');

        $this->writer->log_exceptions(Registry::get_exception_log());

        $error_log      = substr(file_get_contents($this->error_log),0,-2);
        $system_log     = substr(file_get_contents($this->system_log),0,-2);
        $runtime_log    = substr(file_get_contents($this->runtime_log),0,-2);

        $error_log      = json_decode('['.$error_log.']', true);
        $system_log     = json_decode('['.$system_log.']', true);
        $runtime_log    = json_decode('['.$runtime_log.']', true);

        $this->assertTrue(is_array($error_log), 'error.log_write log_read failed');
        $this->assertTrue(is_array($system_log), 'system.log_write log_read failed');
        $this->assertTrue(is_array($runtime_log), 'runtime.log_write log_read failed');

        foreach($exceptions as $exception)
        {
            if($exception instanceof SystemExceptionI)
            {
                $actual = array_shift($system_log);
            }
            elseif($exception instanceof RuntimeExceptionI)
            {
                $actual = array_shift($runtime_log);
            }
            else
            {
                $actual = array_shift($error_log);
            }

            $this->assertTrue(is_array($actual), 'log_write record failed: must be array');
            $this->assertTrue(count($actual) === 8, 'log_write record failed: must have 8 elements');

            $this->assertEquals(8, $actual[0], 'element[0] != 8');

            $this->assertEquals('192.168.1.1', $actual[1], 'element[1] != 192.168.1.1');

            $this->assertMatchesRegularExpression
            (
                '/[0-9]{4}\-[0-9]{2}\-[0-9]{2} [0-9]{2}\:[0-9]{2}\:[0-9]{2}/',
                $actual[2],
                'element[1] must be datetime format: '.$actual[2]
            );

            $this->assertEquals(get_class($exception), $actual[3], 'element[3] must be a class name');
            $this->assertEquals($exception->get_source(), $actual[4], 'element[4] must be a source');
            $this->assertEquals($exception->template(), $actual[5], 'element[5] must be a template');

            if(isset($exception->get_data()['message']) && empty($exception->template()))
            {
                $this->assertEquals($exception->get_data()['message'], $actual[6], 'element[6] must be a message');
            }
            elseif(empty($exception->template()))
            {
                $this->assertEquals($exception->getMessage(), $actual[6], 'element[6] must be a message');
            }
            else
            {
                $this->assertEquals('', $actual[6], 'element[6] must be a message');
            }

            $data       = array_merge(['code' => $exception->getCode()], $exception->get_data());

            $this->assertEquals($data, $actual[7], 'element[7] must be an array of data');
        }

        $this->assertTrue(count($error_log) === 0, 'error log_write not contains more elements');
        $this->assertTrue(count($system_log) === 0, 'system log_write not contains more elements');
        $this->assertTrue(count($runtime_log) === 0, 'runtime log_write not contains more elements');
    }

    /**
     * Этот тест выполняется после testLog_exceptions,
     * и проверяет работу механизма ротации.
     * Если ротация работает,
     * тогда testLog_rotation должен создать новые файлы журналов.
     *
     * Работа теста проверяется только на одном файле error_log_options
     *
     * @depends testLog_exceptions
     */
    public function testLog_rotation()
    {
        // Подготовка теста
        if(is_file($this->dir.'/error_test.log_write'))
        {
            unlink($this->dir.'/error_test.log_write');
        }

        // Копирование предыдущего файла для сравнения
        $this->assertTrue(copy($this->error_log, $this->dir.'/error_test.log_write'), 'Failed copy test log_write');

        /* @var $exceptions \Exceptions\BaseExceptionI[] */
        $exceptions     = [];

        $exceptions[]   = new LoggableException('test message 3', 10);
        $exceptions[]   = new LogicalException('logical message2');
        $exceptions[]   = new MethodNotCallable('test_method4');
        $exceptions[]   = new ObjectNotInitialized($this);
        $exceptions[]   = new UnexpectedMethodMode(__METHOD__, 'test_mode6677');
        $exceptions[]   = new RecursionLimitExceeded(10);
        $exceptions[]   = new RequiredValueEmpty('var', 'var_expected2233');
        $exceptions[]   = new UnexpectedValueType('var', '112233', '11');

        $this->writer->log_exceptions(Registry::get_exception_log());

        // Проверяем, что создался новый журнал:
        $this->assertTrue(is_file($this->dir.'/error-1.log_write'), 'error log_write not exists');
        $this->assertFileEquals($this->dir.'/error_test.log_write', $this->dir.'/error-1.log_write');
    }

    /**
     * Тестирование второй ротации
     * @depends testLog_rotation
     */
    public function testLog_rotation2()
    {
        // Подготовка теста
        if(is_file($this->dir.'/error_test.log_write'))
        {
            unlink($this->dir.'/error_test.log_write');
        }

        // Копирование предыдущего файла для сравнения
        $this->assertTrue(copy($this->error_log, $this->dir.'/error_test.log_write'), 'Failed copy test log_write');

        /* @var $exceptions \Exceptions\BaseExceptionI[] */
        $exceptions     = [];

        $exceptions[]   = new LoggableException('test message 5', 222);
        $exceptions[]   = new LogicalException('logical message4433');
        $exceptions[]   = new MethodNotCallable('test3333444_method');
        $exceptions[]   = new ObjectNotInitialized($this);
        $exceptions[]   = new UnexpectedMethodMode(__METHOD__, 'test666555_mode');
        $exceptions[]   = new RecursionLimitExceeded(10);
        $exceptions[]   = new RequiredValueEmpty('var', 'var_44433expected');
        $exceptions[]   = new UnexpectedValueType('var', '7788', '11');

        $this->writer->log_exceptions(Registry::get_exception_log());

        // Проверяем, что создался новый журнал:
        $this->assertTrue(is_file($this->dir.'/error-2.log_write'), 'error log_write not exists');
        $this->assertFileEquals($this->dir.'/error_test.log_write', $this->dir.'/error-2.log_write');
    }

    /**
     * Тестирование третьей ротации
     * На третей ротации согласно настройкам max_count
     * должно произойти копирование не в следующий файл,
     * а в предыдущий.
     *
     * @depends testLog_rotation2
     */
    public function testLog_rotation3()
    {
        // Подготовка теста
        if(is_file($this->dir.'/error_test.log_write'))
        {
            unlink($this->dir.'/error_test.log_write');
        }

        // Копирование предыдущего файла для сравнения
        $this->assertTrue(copy($this->error_log, $this->dir.'/error_test.log_write'), 'Failed copy test log_write');

        /* @var $exceptions \Exceptions\BaseExceptionI[] */
        $exceptions     = [];

        $exceptions[]   = new LoggableException('test message 88888', 222);
        $exceptions[]   = new LogicalException('logical message4433');
        $exceptions[]   = new MethodNotCallable('test3333444_method');
        $exceptions[]   = new UnexpectedMethodMode(__METHOD__, 'mmmmm');
        $exceptions[]   = new RecursionLimitExceeded(999);

        $this->writer->log_exceptions(Registry::get_exception_log());

        // Проверяем, что создался новый журнал:
        $this->assertTrue(is_file($this->dir.'/error-1.log_write'), 'error log_write not exists');
        $this->assertFileEquals($this->dir.'/error_test.log_write', $this->dir.'/error-1.log_write');
    }
}