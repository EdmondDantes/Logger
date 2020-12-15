<?PHP
namespace IfCastle\Logger\FS;

use Exceptions\BaseExceptionI;
use IfCastle\Logger\WriterI;

/**
 * Общий Logger.
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
 */
class Writer implements WriterI
{
    /**
     * PHP журнал
     */
    const PHPLOG    = 0;
    /**
     * EMAIL
     */
    const MAIL      = 1;
    /**
     * Файл
     */
    const FILE      = 3;
    /**
     * SAPI
     */
    const SAPI      = 4;

    /**
     * System log_write journal
     * @var array
     */
    protected array $system_log_options   = [];

    public function __construct(array $error_log_options)
    {
        $this->system_log_options   = $error_log_options;
    }

    public function log_write(array $records): int|false
    {
        $log                = [];

        foreach($records as $record)
        {
            if(is_array($record))
            {
                $log[]      = json_encode(array_merge([8], $record));
            }
            else
            {
                $log[]      = json_encode(array_merge([8], [$record]));
            }
        }

        if($this->to_log($this->system_log_options, $log))
        {
            return count($log);
        }

        return false;
    }

    /**
     * writes to log
     *
     * @param   array       $options
     * @param   string      $message
     *
     * @return  bool
     */
    protected function to_log(array $options, mixed $message): bool
    {
        if(!is_array($message) || empty($message))
        {
            return true;
        }

        $type               = isset($options[self::TYPE])           ? $options[self::TYPE]          : self::PHPLOG;
        $destination        = isset($options[self::DESTINATION])    ? $options[self::DESTINATION]   : null;
        $headers            = isset($options[self::HEADERS])        ? $options[self::HEADERS]       : '';

        if(self::FILE == $type && !empty($options[self::ROTATION]))
        {
            $errors         = $this->rotation($options);

            if(!empty($errors))
            {
                error_log(implode(",\n", $message));
            }
        }

        return error_log(implode(",\n", $message).",\n", $type, $destination, $headers);
    }

    protected function rotation(array $options): array
    {
        $errors             = [];

        // 1. Выяснить, есть ли текущий файл,
        // если нет - выйти
        if(empty($options[self::DESTINATION]) || !is_file($options[self::DESTINATION]))
        {
            return $errors;
        }

        // 2. Если размер файла выше, чем размер по умолчанию

        // 1Mb
        $maxsize            = self::DEFAULT_MAXSIZE;

        if((int)$options[self::MAXSIZE] > 0)
        {
            $maxsize        = (int)$options[self::MAXSIZE];
        }

        if(filesize($options[self::DESTINATION]) < $maxsize)
        {
            return $errors;
        }

        // 3. Иначе текущий файл ротируется
        // он получает новое имя по алгоритму ротации

        // 3.1. Проанализировать имя файла, расширение и прочее
        $path               = pathinfo($options[self::DESTINATION]);
        $ext                = '';

        if(array_key_exists('extension', $path))
        {
            $ext            = '.'.$path['extension'];
        }

        $base_name          = basename($path['basename'], $ext);

        // Список будет отсортирован позже, функцией натуральной сортировки,
        // так как glob сортирует его при помощи как alphabetically
        $files = glob($path['dirname'].'/'.$base_name.'-[0-9]'.$ext, GLOB_NOSORT);

        // Если массив пустой - новое имя файла 1
        if(!is_array($files) || count($files) === 0)
        {
            $count          = 0;
        }
        else
        {
            // Иначе натуральная сортировка
            natsort($files);

            $m              = [];

            preg_match('/'.preg_quote($base_name).'\-([0-9]+)'.preg_quote($ext).'$/i', array_pop($files), $m);

            $count = (int)$m[1];
        }

        // Счётчик увеличивается на 1
        $count++;

        if(!empty($options['max_count']) && $count > $options['max_count'])
        {
            $count          = 1;
        }

        $new_file_name = $path['dirname'].'/'.$base_name.'-'.$count.$ext;
        // Если файл уже есть - он будет перезаписан
        if(is_file($new_file_name))
        {
            unlink($new_file_name);
        }
        if(is_file($new_file_name))
        {
            $errors[]       = json_encode
            ([
                8,
                // remote_address
                '',
                // date
                '',
                // type
                BaseExceptionI::EMERGENCY,
                // source
                __FILE__.':'.__LINE__,
                // template
                'Rotation problem: next file exists and failed unlink (count = {count}, file = {file})',
                // message
                '',
                // ex data
                ['count' => $count, 'file' => $new_file_name]
            ]);

            return $errors;
        }

        if(!rename($options['destination'] ,$new_file_name))
        {
            $errors[]       = json_encode
            ([
                8,
                '',
                '',
                BaseExceptionI::EMERGENCY,
                __FILE__.':'.__LINE__,
                'Rotation problem: rename file {new_file} was failed',
                '',
                ['new_file' => $new_file_name]
            ]);

            return $errors;
        }

        return [];
    }
}