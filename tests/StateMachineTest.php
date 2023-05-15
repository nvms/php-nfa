<?php

/*
 * ┌----------------------------------------------------------------------┐
 * | This file is part of php-nfa (https://github.com/nvms/php-nfa)       |
 * ├----------------------------------------------------------------------┤
 * | Copyright (c) nvms (https://github.com/nvms/php-nfa)                 |
 * | Licensed under the MIT License (https://opensource.org/licenses/MIT) |
 * └----------------------------------------------------------------------┘
 */

\error_reporting(\E_ALL);
\ini_set('display_errors', 'stdout');

// enable assertions
\ini_set('assert.active', 1);
@\ini_set('zend.assertions', 1);
\ini_set('assert.exception', 1);

\header('Content-type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';

use NVMS\NFA\Condition;
use NVMS\NFA\PostTransition;
use NVMS\NFA\StateMachine;
use NVMS\NFA\StateTick;
use NVMS\NFA\Transition;
use PHPUnit\Framework\TestCase;

class Human extends StateMachine
{
    public const ALIVE = 0;
    public const ASLEEP = 1;
    public const AWAKE = 2;
    public const HUNGRY = 3;
    public const THIRSTY = 4;
    public const WALKING = 5;
    public const RUNNING = 6;
    public const BORED = 7;
    public const EATING = 8;
    public const EXHAUSTED = 9;

    public $hunger = 0;
    public $thirst = 0;
    public $sleepiness = 100;
    public $happiness = 50;
    public $boredom = 10;

    public static function initial()
    {
        return [Human::ALIVE, Human::ASLEEP, Human::BORED];
    }
}

class Animal extends StateMachine
{
}

final class StateMachineTest extends TestCase
{
    public function testNoInitialStateDefined(): void
    {
        $animal = new Animal();

        $this->assertEquals(0, count($animal->states));
    }

    public function testNullStateName(): void
    {
        $animal = new Animal();

        $this->assertEquals(null, $animal->getStateNameById(null));
    }

    public function testConditionsNotMet(): void
    {
        $human = new Human();
        $transition = new Transition(
            Human::AWAKE,
            Human::ASLEEP,
            new Condition('[ALIVE]', '{hunger} > 0')
        );

        $this->assertEquals(false, $human->conditionsMet($transition->conditions));
    }

    public function testCanCreateMachine(): void
    {
        $human = new Human();

        $this->assertTrue($human->is(Human::ALIVE));
        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertTrue($human->is(Human::BORED));

        $this->assertInstanceOf(
            StateMachine::class,
            $human
        );
    }

    public function testGetStateNameById(): void
    {
        $human = new Human();

        $this->assertEquals('BORED', $human->getStateNameById(7));
    }

    public function testGetStateNames(): void
    {
        $human = new Human();

        $names = ['ALIVE', 'ASLEEP', 'BORED'];

        $this->assertEquals($names, $human->getStateNames());
    }

    public function testAddTransition(): void
    {
        $human = new Human();

        $transition = $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]')
            )
        );

        $this->assertInstanceOf(
            Transition::class,
            $transition
        );
    }

    public function testPostTransition(): void
    {
        $human = new Human();

        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]'),
                new PostTransition('<sleepiness> = 0')
            )
        );

        $this->assertTrue(100 === $human->sleepiness);
        $human->turn();
        $this->assertTrue(0 === $human->sleepiness);
    }

    public function testCanDoTransition(): void
    {
        $human = new Human();

        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]'),
                new PostTransition('<sleepiness> = 0')
            )
        );

        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertTrue(!$human->is(Human::AWAKE));
        $this->assertTrue(100 === $human->sleepiness);

        $human->turn(true);

        $this->assertTrue(!$human->is(Human::ASLEEP));
        $this->assertTrue($human->is(Human::AWAKE));
        $this->assertTrue(0 === $human->sleepiness);
    }

    public function testTransitionConditions(): void
    {
        $human = new Human();

        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]')
            )
        );

        $human->addTransition(
            new Transition(
                null,
                Human::HUNGRY,
                new Condition('{hunger} >= 50', '[ALIVE]', '[AWAKE]')
            )
        );

        $this->assertTrue(0 === $human->hunger);
        $this->assertTrue(!$human->is(Human::HUNGRY));
        $human->hunger = 50;
        $human->turn(true);
        $this->assertTrue($human->is(Human::HUNGRY));
    }

    public function testSaveLoad(): void
    {
        $human = new Human();

        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]')
            )
        );

        $human->turn();
        $human->save('human');

        $loaded = StateMachine::load('human');

        $this->assertInstanceOf(
            StateMachine::class,
            $loaded
        );

        $this->assertTrue($loaded->is(Human::AWAKE));
        $this->assertTrue(!$loaded->is(Human::ASLEEP));

        StateMachine::delete('human');
    }

    public function testStateTick(): void
    {
        $human = new Human();

        $this->assertTrue(100 === $human->sleepiness);

        $human->tick(
            new StateTick(
                Human::ASLEEP,
                '<sleepiness> = {sleepiness} - 15',
                1
            )
        );

        sleep(1);
        $human->turn();
        $this->assertTrue(85 === $human->sleepiness);
        $human->save('human');

        sleep(2);
        $loaded = StateMachine::load('human');
        $this->assertEquals(85, $loaded->sleepiness);
        $loaded->turn();
        $this->assertTrue(55 === $loaded->sleepiness);

        StateMachine::delete('human');

        $loaded->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('{sleepiness} <= 0')
            )
        );

        /*
         * This tick, combined with the tick above, should decrement
         * sleepiness to exactly 0 after another second has passed.
         */
        $loaded->tick(
            new StateTick(
                Human::ASLEEP,
                '<sleepiness> = {sleepiness} - 40',
                1
            )
        );
        sleep(1);
        $loaded->turn();

        $this->assertTrue(0 === $loaded->sleepiness);
        $this->assertTrue($loaded->is(Human::AWAKE));
        $this->assertTrue(!$loaded->is(Human::ASLEEP));
    }

    public function testActivate(): void
    {
        $human = new Human();
        $human->tick(
            new StateTick(
                Human::ASLEEP,
                '<sleepiness> = {sleepiness} - 50',
                1
            )
        );
        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('{sleepiness} <= 0')
            )
        );

        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertEquals(100, $human->sleepiness);

        $human->activate(Human::ASLEEP);

        $this->assertTrue(!$human->is(Human::ASLEEP));
        $this->assertEquals(0, $human->sleepiness);
    }

    public function testNew(): void
    {
        $human = new Human();
        $this->assertEquals(0, $human->hunger);
        $human->hunger = 25;

        $bob = $human->new();
        $this->assertEquals(25, $bob->hunger);

        $this->assertTrue(spl_object_hash($human) !== spl_object_hash($bob));
    }

    public function testPostTransitionCallable(): void
    {
        $called = false;
        $callback = function () use (&$called) {
            $called = true;
        };

        $human = new Human();
        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]'),
                new PostTransition($callback, '<sleepiness> = 0')
            )
        );

        $this->assertTrue(!$called);
        $human->turn();
        $this->assertTrue($called);
        $this->assertEquals(0, $human->sleepiness);
    }

    public function testNotInStateCondition(): void
    {
        $human = new Human();

        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[!ALIVE]')
            )
        );

        $this->assertTrue($human->is(Human::ALIVE));
        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertTrue($human->not(Human::AWAKE));
        $human->turn();
        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertTrue($human->not(Human::AWAKE));
    }
}
