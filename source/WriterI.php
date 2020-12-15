<?PHP
namespace IfCastle\Logger;

/**
 * Interface for the exception and error logger
 */
interface WriterI
{
    /**
     * option: type
     */
    const TYPE              = 'type';
    /**
     * option: destination
     */
    const DESTINATION       = 'destination';
    /**
     * option: headers
     */
    const HEADERS           = 'headers';
    /**
     * option: rotation
     */
    const ROTATION          = 'rotation';
    /**
     * option: maxsize
     */
    const MAXSIZE           = 'maxsize';
    /**
     * Max Count
     */
    const MAX_COUNT         = 'max_count';
    /**
     * option: REMOTE_ADDRESS
     */
    const REMOTE_ADDRESS    = 'remote_address';
    /**
     * option: DEBUG level
     */
    const DEBUG_LEVEL       = 'debug_level';

    /**
     * default file max size for rotation
     * (1M)
     */
    const DEFAULT_MAXSIZE   = 1048576;

    /**
     * Метод журнализирует массив записей.
     * Каждый элемент массива может быть либо
     * 1. Строкой
     * 2. Или массивом с полями
     *
     * @param       array       $records        Список записей, которые будут помещены в лог.
     *
     * @return      int|false                   Количество обработанных записей
     */
    public function log_write(array $records): int|false;
}