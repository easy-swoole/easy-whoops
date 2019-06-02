<?php
/**
 * Whoops - php errors for cool kids
 * @author Filipe Dobreira <http://github.com/filp>
 */

namespace EasySwoole\Whoops\Handler;

use EasySwoole\Whoops\Exception\Inspector;
use EasySwoole\Whoops\RunInterface;

/**
 * Abstract implementation of a Handler.
 */
abstract class Handler implements HandlerInterface
{
    /*
     Return constants that can be returned from Handler::handle
     to message the handler walker.
     */
    const DONE = 0x10; // returning this is optional, only exists for
    // semantic purposes
    /**
     * The Handler has handled the Throwable in some way, and wishes to skip any other Handler.
     * Execution will continue.
     */
    const LAST_HANDLER = 0x20;
    /**
     * The Handler has handled the Throwable in some way, and wishes to quit/stop execution
     */
    const QUIT = 0x30;

    /**
     * @var RunInterface
     */
    private $run;

    /**
     * @var Inspector $inspector
     */
    private $inspector;

    /**
     * @var \Throwable $exception
     */
    private $exception;

    /**
     * @var string $handleContent
     */
    private $handleContent;

    /**
     * @param RunInterface $run
     */
    public function setRun(RunInterface $run)
    {
        $this->run = $run;
    }

    /**
     * @return RunInterface
     */
    protected function getRun()
    {
        return $this->run;
    }

    /**
     * @param Inspector $inspector
     */
    public function setInspector(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @return Inspector
     */
    protected function getInspector()
    {
        return $this->inspector;
    }

    /**
     * @param \Throwable $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @return \Throwable
     */
    protected function getException()
    {
        return $this->exception;
    }

    /**
     * 设置当前Handle输出的内容
     * @param $content
     */
    public function setHandleContent($content)
    {
        $this->handleContent = $content;
    }

    /**
     * 获取当前Handle输出的内容
     * @return mixed|string
     */
    public function getHandleContent()
    {
        return $this->handleContent;
    }
}
