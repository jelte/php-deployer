<?php


namespace Automaton\Server;


use Automaton\Server\Ssh\ConnectionInterface;

abstract class AbstractServer implements ServerInterface
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    protected $root;

    protected $cwd;

    public function __construct($name, ConnectionInterface $connection, $root = null)
    {
        $this->name = $name;
        $this->connection = $connection;
        $this->root = $root;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function cwd($path)
    {
        if ( $this->cwd == null ) {
            $this->cwd = $this->root;
            if ( substr($this->cwd,0,1) != DIRECTORY_SEPARATOR && substr($this->cwd,0,1) != '~' ) {
                $this->cwd = '~/'.$this->cwd;
            }
            $this->cwd = trim($this->cwd, DIRECTORY_SEPARATOR);
        }
        return $this->cwd.DIRECTORY_SEPARATOR.$path;
    }

    public function cd($path)
    {
        if ( substr($path, 0, 1) !== DIRECTORY_SEPARATOR ) {
            $path = (null !== $this->cwd?$this->cwd:$this->root).DIRECTORY_SEPARATOR.$path;
        }
        $this->run("cd {$path}");
    }

    public function __call($method, $arguments)
    {
        if ( method_exists($this->connection, $method) ) {
            return call_user_func_array(array($this->connection, $method), $arguments);
        }
    }
}
