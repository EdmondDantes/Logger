<?PHP
namespace IfCastle\Logger\FS;

use Exceptions\BaseExceptionI;
use Exceptions\SaveHandlerI;
use Exceptions\SystemExceptionI;
use Exceptions\RuntimeExceptionI;

use IfCastle\Logger\ErrorsWriterI;

/**
 * Writer для записи ошибок в журналы.
 *
 * Writer поддерживает четыре вида журналов:
 * PHPLOG - журнал PHP ошибок
 * MAIL   - почтовый адрес
 * FILE   - файл
 * SAPI   - SAPI
 *
 * Writer записывает данные в файл, в формате JSON без обрамляющих символов [...],
 * в котором отдельные сообщения разделены символом \n.
 * Этот формат позволяет обрабатывать файл как по строкам, так и целиком.
 *
 * Для файлов Writer поддерживает алгоритм ротирования журналов со счётчиком.
 * При этом он позволяет указывать максимальный размер журнала,
 * и другие параметры.
 *
 * Проблемы в логировании подаются в PHP ERROR LOG, а не в общий журнал.
 *
 */
class ErrorsWriter extends Writer implements    ErrorsWriterI,
                                                SaveHandlerI
{
    /**
     * Options
     * @var array
     */
    protected array $options              = [];
    /**
     * Журнал для ошибок программиста
     * @var array
     */
    protected array $error_log_options    = [];
    /**
     * Журнал для системных ошибок
     * @var array
     */
    protected array $system_log_options   = [];
    /**
     * Журнал для ошибок времени выполнения
     * @var array
     */
    protected array $runtime_log_options  = [];

    /**
     * @param   array $options
     * @param   array $error_log_options
     * @param   array $system_log_options
     * @param   array $runtime_log_options
     */
    public function __construct
    (
        array $options,
        array $error_log_options,
        array $system_log_options   = [],
        array $runtime_log_options  = []
    )
    {
        parent::__construct($system_log_options);

        $this->options              = $options;
        $this->error_log_options    = $error_log_options;
        $this->system_log_options   = $system_log_options;
        $this->runtime_log_options  = $runtime_log_options;
    }

    public function log_exceptions($exceptions): int
    {
        if(!is_array($exceptions))
        {
            $exceptions             = [$exceptions];
        }

        $error_log                  = [];
        $runtime_log                = [];
        $system_log                 = [];

        // Вычисление константных величин
        $time                       = time();
        $date                       = date('Y-m-d H:i:s', $time);
        $remote_address             = '';

        if(!empty($this->options[self::REMOTE_ADDRESS]))
        {
            $remote_address         = $this->options[self::REMOTE_ADDRESS];
        }

        $counter                    = 0;

        foreach($exceptions as $exception)
        {
            if($exception instanceof BaseExceptionI)
            {
                if(!$exception->is_loggable())
                {
                    continue;
                }

                $array      = $exception->to_array();

                if(!array_key_exists('data', $array))
                {
                    $array['data']              = [];
                }

                if(array_key_exists('code', $array) && !isset($array['data']['code']))
                {
                    $array['data']['code']      = $array['code'];
                }

                /**
                 * The first element "8" - this is signature for simple test
                 * that this block corrected
                 */

                $record = json_encode
                ([
                    8,
                    $remote_address,
                    $date,
                    isset($array['type'])       ? $array['type']        : '',
                    isset($array['source'])     ? $array['source']      : '',
                    isset($array['template'])   ? $array['template']    : '',
                    isset($array['message'])    ? $array['message']     : '',
                    isset($array['data'])       ? $array['data']        : [],
                ]);

                if($exception instanceof SystemExceptionI)
                {
                    $system_log[]               = $record;
                }
                elseif($exception instanceof RuntimeExceptionI)
                {
                    $runtime_log[]              = $record;
                }
                else
                {
                    $error_log[]                = $record;
                }
            }
            elseif($exception instanceof \Throwable)
            {
                $error_log[] = json_encode(array
                (
                    8,
                    $remote_address,
                    $date,
                    // type
                    get_class($exception),
                    // source
                    $exception->getFile().':'.$exception->getLine(),
                    // template
                    '',
                    // message
                    $exception->getMessage(),
                    // ex data
                    ['code' => $exception->getCode()]
                ));
            }

            $counter++;
        }

        if($this->write_logs($error_log, $system_log, $runtime_log))
        {
            return $counter;
        }

        return false;
    }

    /**
     * Save handler method
     *
     * @param       array              $exceptions
     * @param       callable           $reset_log
     * @param       array|\ArrayAccess $logger_options
     * @param       array|\ArrayAccess $debug_options
     *
     * @return      void
     */
    public function save_exceptions(array $exceptions, callable $reset_log, $logger_options = [], $debug_options = [])
    {
        $this->log_exceptions($exceptions);

        $reset_log();
    }

    protected function write_logs($error_log, $system_log, $runtime_log)
    {
        if(!empty($this->system_log_options))
        {
            $this->to_log($this->system_log_options, $system_log);
        }
        else
        {
            $error_log                  = array_merge($error_log, $system_log);
        }

        if(!empty($this->runtime_log_options))
        {
            $this->to_log($this->runtime_log_options, $runtime_log);
        }
        else
        {
            $error_log                  = array_merge($error_log, $runtime_log);
        }

        return $this->to_log($this->error_log_options, $error_log);
    }
}