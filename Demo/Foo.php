<?php

namespace Demo;

class Foo
{
    public function hello()
    {
        return "Hello";
    }

    public function helloName($name, $surname)
    {
        return "Hello " . $name . " " . $surname;
    }

    public function getUsers()
    {
        return array('Gonzalo', 'Peter', );
    }
}