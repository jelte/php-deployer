<?php


namespace Automaton\Recipe;

use Automaton\Task\Alias;
use Automaton\Task\Task;


class Recipe
{
    protected $classname;

    protected $reader;

    public function __construct($classname)
    {
        $this->classname = $classname;
        $this->reader = new AnnotationReader();
    }

    public function tasks()
    {
        $reflection = new \ReflectionClass($this->classname);
        $recipe = $reflection->newInstance();
        $prefix = str_replace("\\", ":", strtolower(substr($reflection->getName(), strpos($reflection->getName(), 'Recipes') + 8)));
        $tasks = array();
        foreach ($reflection->getMethods() as $method) {
            if ($annotation = $this->reader->getMethodAnnotation($method, 'Automaton\Recipe\Annotation\Task')) {
                $task = new Task($prefix . ':' . $method->getName(), $annotation->description, array($recipe, $method->getName()), $annotation->public, $annotation->progress);
                $tasks[] = $task;
                if ($alias = $this->reader->getMethodAnnotation($method, 'Automaton\Recipe\Annotation\Alias')) {
                    $tasks[] = new Alias($alias->name, $task, $alias->public, $annotation->progress);
                }
            }
        }
        return $tasks;
    }

    public function befores()
    {
        return $this->getAdditionalTasks('before');
    }

    public function afters()
    {
        return $this->getAdditionalTasks('after');
    }

    protected function getAdditionalTasks($type)
    {
        $reflection = new \ReflectionClass($this->classname);
        $prefix = str_replace("\\", ":", strtolower(substr($reflection->getName(), strpos($reflection->getName(), 'Recipes') + 8)));
        $tasks = array();
        foreach ($reflection->getMethods() as $method) {
            foreach ($this->reader->getMethodAnnotations($method, 'Automaton\Recipe\Annotation\\'.ucfirst($type)) as $annotation) {
                $tasks[] = array($prefix . ':' . $method->getShortName(), $annotation->task, $annotation->priority);
            }
        }
        return $tasks;
    }
}
