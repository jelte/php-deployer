<?php


namespace Automaton\Tests\Server;


use Automaton\Console\Command\Event\TaskEvent;
use Automaton\RuntimeEnvironment;
use Automaton\Server\ServerPluginEventSubscriber;
use Prophecy\PhpUnit\ProphecyTestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Automaton\Task\ExecutableTaskInterface;

class ServerPluginEventSubscriberTest extends ProphecyTestCase
{
    protected $plugin, $eventDispatcher, $input, $output, $task, $taskEvent, $runtimeEnvironment;

    /**
     * @var ServerPluginEventSubscriber
     */
    protected $subscriber;

    public function setUp()
    {
        parent::setUp();
        $this->plugin = $this->getMock('Automaton\Server\ServerPlugin');
        $this->eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->task = $this->getMock('Automaton\Task\TaskInterface');
        $this->input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $this->runtimeEnvironment = $this->getMock('Automaton\RuntimeEnvironment', array(), array($this->input, $this->output, new ParameterBag(), new HelperSet()));
        $this->taskEvent = $this->getMock('Automaton\Console\Command\Event\TaskEvent', array(), array($this->task, $this->runtimeEnvironment));

        $this->subscriber = new ServerPluginEventSubscriber($this->plugin, $this->eventDispatcher);
    }

    /**
     * @test
     */
    public function hasSubscribedEvents()
    {
        $this->assertInternalType('array', $this->subscriber->getSubscribedEvents());
    }

    /**
     * @test
     */
    public function addsServerParameter()
    {
        $taskCommand = $this->getMock('Automaton\Console\Command\RunTaskCommand', array(), array(), '', false);
        $taskCommandEvent = $this->getMock('Automaton\Console\Command\Event\TaskCommandEvent', array(), array($taskCommand));

        $taskCommandEvent->expects($this->exactly(2))->method('getCommand')->willReturn($taskCommand);

        $taskCommand->expects($this->exactly(2))->method('addOption');

        $this->subscriber->configureTaskCommand($taskCommandEvent);
    }

    /**
     * @test
     */
    public function setsServersOnPreRun()
    {
        $servers = array('server-1' => $this->getMock('Automaton\Server\ServerInterface'), 'server-2' => $this->getMock('Automaton\Server\ServerInterface'));
        $this->taskEvent->expects($this->once())->method('getRuntimeEnvironment')->willReturn($this->runtimeEnvironment);
        $this->plugin->expects($this->once())->method('all')->willReturn($servers);
        $this->runtimeEnvironment->expects($this->once())->method('getInput')->willReturn($this->input);
        $this->runtimeEnvironment->expects($this->once())->method('set')->with($this->equalTo('servers'), $this->equalTo($servers));
        $this->subscriber->preTaskRun($this->taskEvent);
    }

    /**
     * @test
     */
    public function setsSpecificServerOnPreRun()
    {

        $servers = array('server-1' => $this->getMock('Automaton\Server\ServerInterface'), 'server-2' => $this->getMock('Automaton\Server\ServerInterface'));
        $keys = array_keys($servers);
        $this->taskEvent->expects($this->once())->method('getRuntimeEnvironment')->willReturn($this->runtimeEnvironment);
        $this->plugin->expects($this->once())->method('all')->willReturn($servers);

        $this->input->expects($this->exactly(2))->method('getOption')->withConsecutive(
            $this->equalTo('dry-run'), $this->equalTo('server')
        )->will($this->onConsecutiveCalls(false, $keys[0]));
        $this->runtimeEnvironment->expects($this->once())->method('getInput')->willReturn($this->input);
        $this->runtimeEnvironment->expects($this->once())->method('set')->with($this->equalTo('servers'), $this->equalTo(array('server-1' => $servers['server-1'])));
        $this->subscriber->preTaskRun($this->taskEvent);
    }

    /**
     * @test
     */
    public function convertsServersToDryRunOnPreRun()
    {
        $env = new RuntimeEnvironment($this->input, $this->output, new ParameterBag(), new HelperSet());
        $servers = array('server-1' => $this->getMock('Automaton\Server\ServerInterface'), 'server-2' => $this->getMock('Automaton\Server\ServerInterface'));
        $keys = array_keys($servers);
        $this->taskEvent->expects($this->once())->method('getRuntimeEnvironment')->willReturn($env);
        $this->plugin->expects($this->once())->method('all')->willReturn($servers);

        $this->input->expects($this->exactly(2))->method('getOption')->withConsecutive(
            $this->equalTo('dry-run'), $this->equalTo('server')
        )->will($this->onConsecutiveCalls(true, false));
        $this->subscriber->preTaskRun($this->taskEvent);
        $servers = $env->get('servers');
        $this->assertInternalType('array', $servers);
        $this->assertArrayHasKey($keys[0], $servers);
        $this->assertInstanceOf('Automaton\Server\DryRunServer', $servers[$keys[0]]);
    }

    /**
     * @test
     */
    public function runsTaskForEachServerOnRun()
    {
        $servers = array('server-1' => null, 'server-2' => null);
        $task = $this->prophesize('Automaton\Task\ExecutableTaskInterface');
        $method = $this->prophesize('ReflectionMethod');
        $serverParam = $this->prophesize('ReflectionParameter');


        $this->taskEvent->expects($this->once())->method('getTask')->willReturn($task->reveal());
        $this->taskEvent->expects($this->once())->method('getRuntimeEnvironment')->willReturn($this->runtimeEnvironment);
        $task->getCallable()->willReturn($method->reveal());
        $method->getParameters()->willReturn(array($serverParam->reveal()));
        $serverParam->getName()->willReturn('server');

        $this->runtimeEnvironment->expects($this->once())->method('get')->with('servers', array())->willReturn($servers);
        $this->eventDispatcher->expects($this->exactly(count($servers)))->method('dispatch')->with('automaton.task.do_invoke');
        $this->taskEvent->expects($this->once())->method('stopPropagation');

        $this->subscriber->onInvoke($this->taskEvent);
    }
}