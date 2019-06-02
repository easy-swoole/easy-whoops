<?php
/**
 * Whoops - php errors for cool kids
 * @author Filipe Dobreira <http://github.com/filp>
 */

namespace EasySwoole\Whoops\Handler;

use EasySwoole\Whoops\Exception\Inspector;
use EasySwoole\Whoops\RunInterface;

interface HandlerInterface
{
    /**
     * @return int|null A handler may return nothing, or a Handler::HANDLE_* constant
     */
    public function handle();

    /**
     * @param RunInterface $run
     * @return void
     */
    public function setRun(RunInterface $run);

    /**
     * @param \Throwable $exception
     * @return void
     */
    public function setException($exception);

    /**
     * @param Inspector $inspector
     * @return void
     */
    public function setInspector(Inspector $inspector);

    /**
     * 实现本方法以获得Handle的显示内容
     * @return mixed
     */
    public function getHandleContent();
}
