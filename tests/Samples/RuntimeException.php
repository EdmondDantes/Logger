<?PHP
namespace Samples;

/**
 * Класс для исключений
 * аспекта Runtime.
 * Исключения этого класса _попадают в журнал_.
 */
class RuntimeException              extends \Exceptions\RuntimeException
{
    /**
     * Флаг логирования.
     * Если флаг равен true - то исключение
     * собирается быть записанным в журнал.
     *
     * @var         boolean
     */
    protected bool $is_loggable  = true;
}