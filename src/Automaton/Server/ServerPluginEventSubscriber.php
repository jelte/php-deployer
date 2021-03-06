<?php


namespace Automaton\Server;

use Automaton\Console\Command\Event\InvokeEvent;
use Automaton\Console\Command\Event\TaskCommandEvent;
use Automaton\Console\Command\Event\TaskEvent;
use Automaton\Plugin\AbstractPluginEventSubscriber;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ServerPluginEventSubscriber extends AbstractPluginEventSubscriber
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(ServerPlugin $plugin, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($plugin);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'automaton.task_command.configure' => 'configureTaskCommand',
            'automaton.task.pre_run' => array('preTaskRun', 99),
            'automaton.task.invoke' => array('onInvoke',10)
        );
    }

    /**
     * @param TaskCommandEvent $event
     */
    public function configureTaskCommand(TaskCommandEvent $event)
    {
        $event->getCommand()->addOption('server', 's', InputOption::VALUE_OPTIONAL, 'Force to run on a specific server');
        $event->getCommand()->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry-Run');
    }

    /**
     * @param TaskEvent $event
     */
    public function preTaskRun(TaskEvent $event)
    {
        $environment = $event->getRuntimeEnvironment();
        $input = $environment->getInput();
        $servers = $this->plugin->all();

        if ( $input->getOption('dry-run') ) {
            $output = $environment->getOutput();
            $servers = array_map(function($value) use ($output) {
               return new DryRunServer($value, $output);
            },$servers);
        }
        if ($serverName = $input->getOption('server')) {
            $servers = array($serverName => $servers[$serverName]);
        }
        array_walk($servers, function(ServerInterface $server) use ( $environment ){
            $server->setOutput($environment->getOutput());
        });
        $environment->set('servers',$servers);
    }

    /**
     * @param TaskEvent $taskEvent
     */
    public function onInvoke(TaskEvent $taskEvent)
    {
        $environment = $taskEvent->getRuntimeEnvironment();
        $task = $taskEvent->getTask();
        if ( $this->hasServerParameter($task->getCallable()) ) {
            $servers = $environment->get('servers', array());
            foreach ($servers as $server) {
                $environment->set('server', $server);
                $this->eventDispatcher->dispatch('automaton.task.do_invoke', new InvokeEvent($task, $environment));
            }
            $taskEvent->stopPropagation();
        }
    }

    protected function hasServerParameter($callable)
    {
        if ( is_array($callable) ) {
            list(, $method) = $callable;
            $callable = $method;
        }
        foreach ($callable->getParameters() as $parameter) {
            if ( $parameter->getName() == 'server' ) {
                return true;
            }
        }
        return false;
    }
}
