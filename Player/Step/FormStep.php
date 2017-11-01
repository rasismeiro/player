<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Blackfire\Player\Step;

/**
 * Description of FormStep
 *
 * @author sismeiro
 */
class FormStep extends Step
{

    private $selector;
    private $parameters = [];
    private $body;

    public function __construct($selector, $file = null, $line = null)
    {
        $this->selector = $selector;

        parent::__construct($file, $line);
    }

    public function __toString()
    {
        return sprintf("└ %s: %s\n", get_class($this), $this->selector);
    }

    public function param($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function body($body)
    {
        $this->body = $body;
    }

    public function getSelector()
    {
        return $this->selector;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getBody()
    {
        return $this->body;
    }

}
