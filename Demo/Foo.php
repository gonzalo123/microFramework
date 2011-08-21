<?php

namespace Demo;

class Foo
{
    public function hello()
    {
        return "Hello";
    }

    public function helloName($name)
    {
        return "Hello " . $name;
    }

    public function getUsers()
    {
        return array('Gonzalo', 'Peter', );
    }
}