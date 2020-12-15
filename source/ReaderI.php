<?PHP
namespace IfCastle\Logger;

/**
 * interface for reading logs
 */
interface ReaderI
{
    /**
     * Reads from log
     *
     * @return  array
     *
     * @throws  \Exception
     */
    public function log_read(): array;

    /**
     * Returns TRUE if the log done.
     * @return boolean
     */
    public function log_is_end(): bool;
}