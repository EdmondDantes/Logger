<?PHP
namespace IfCastle\Logger;

/**
 * Interface for the BaseExceptionI exception and error logger
 */
interface ErrorsWriterI
{
    /**
     * Journaled list of exceptions
     *
     * @param       \Exceptions\BaseExceptionI[]|\Throwable[] $exceptions     List of exceptions
     *
     * @return      int                             Count of written items
     */
    public function log_exceptions($exceptions): int;
}