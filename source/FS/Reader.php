<?PHP
namespace IfCastle\Logger\FS;

use IfCastle\Logger\ReaderI;

/**
 * Класс для обработки журналов.
 *
 * Класс умеет читать журналы в ротационной форме,
 * или в простой.
 * В ротационной форме последним файлом читается файл без цифры.
 *
 *
 */
class Reader        implements  ReaderI
{
    /**
     * @var string
     */
    protected string $destination;
    /**
     * @var bool
     */
    protected bool $rotation        = false;
    /**
     * @var int
     */
    protected int $max_count        = 0;
    /**
     * @var int
     */
    protected int $read_buff_size   = 1048576;
    /**
     * @var array
     */
    protected array $current_pos    = [];
    /**
     * @var array|null
     */
    protected ?array $files;
    /**
     * @var resource
     */
    protected $fh;

    public function __construct(array $options)
    {
        if(empty($options['destination'])
        || empty($options['type'])
        || $options['type'] !== Writer::FILE)
        {
            throw new \Exception('log options is failed');
        }

        $this->destination      = $options['destination'];

        if(!empty($options['rotation']))
        {
            $this->rotation     = true;
        }

        if(!empty($options['max_count']))
        {
            $this->max_count    = (int) $options['max_count'];
        }

        if(!empty($options['read_buff_size']))
        {
            $this->read_buff_size = (int) $options['read_buff_size'];
        }

        if(!empty($options['current_pos']) && is_array($options['current_pos']))
        {
            $this->current_pos  = $options['current_pos'];
        }
    }

    public function __destruct()
    {
        if($this->fh)
        {
            fclose($this->fh);
        }
    }

    public function log_is_end(): bool
    {
        if($this->rotation && count($this->files) > 0)
        {
            return false;
        }

        if($this->fh)
        {
            return feof($this->fh);
        }

        return true;
    }


    public function log_read(): array
    {
        if(empty($this->current_pos['file']))
        {
            // Если определён алгоритм ротации,
            // тогда используем список файлов
            if($this->rotation)
            {
                if(!is_array($this->files))
                {
                    $this->get_files();
                }

                if(count($this->files) === 0)
                {
                    return [];
                }

                $file       = null;

                while(count($this->files) > 0)
                {
                    $file = array_shift($this->files);

                    if(is_file($file) && is_readable($file))
                    {
                        break;
                    }
                }

                if($file === null)
                {
                    return [];
                }

                $this->current_pos = ['file' => $file];
            }
            else
            {
                $this->current_pos = ['file' => $this->destination];
            }

        }

        $data               = $this->read_file();

        if($data === '')
        {
            return [];
        }

        // Проверка на валидность чтения данных из файла
        if(strpos($data, '[8,') !== 0 || substr($data, -2) !== ",\n")
        {
            throw new \Exception('failed data format: ');
        }

        $data               = substr($data, 0, -2);

        $data               = json_decode('['.$data.']', true);

        if(!is_array($data))
        {
            throw new \Exception('log_write format failed');
        }

        return $data;
    }

    protected function read_file(): string
    {
        $file               = $this->current_pos['file'];
        $pos                = 0;

        if(!empty($this->current_pos['pos']))
        {
            $pos            = $this->current_pos['pos'];
        }

        if($this->read_buff_size > filesize($file) && $pos === 0)
        {
            $res = file_get_contents($file);
            $this->current_pos = [];

            if(false === $res)
            {
                throw new \Exception('failed log_read file: '.$file);
            }
        }

        $buff_size          = $this->read_buff_size;

        if(!$this->fh)
        {
            $this->fh       = fopen($file, 'r');

            if(!$this->fh)
            {
                throw new \Exception('failed open file: '.$file);
            }

            if($pos)
            {
                if(fseek($this->fh, $pos) === -1)
                {
                    $this->current_pos = [];
                    return '';
                }
            }

        }

        $buffer             = '';

        while (($res = fgets($this->fh, 4096)) !== false && $buff_size < $buffer)
        {
            $buffer        .= $res;
        }

        return $buffer;
    }

    protected function get_files(): void
    {
        // 3.1. Проанализировать имя файла, расширение и прочее
        $path               = pathinfo($this->destination);
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
            $this->files    = [];
        }
        else
        {
            // Иначе натуральная сортировка
            natsort($files);
            $this->files    = $files;
        }

        return;
    }
}