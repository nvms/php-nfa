# php-nfa

Expressive non-deterministic state machines.

100% test coverage.

## Requirements

 * PHP 7.2+

## Installation

 1. Include the library via Composer.

    ```bash
    $ composer require nvms/php-nfa
    ```

 1. Include the Composer autoloader:

    ```bash
    require __DIR__.'/vendor/autoload.php';
    ```

## Usage

### Extending the `StateMachine` class

```php
class Human extends StateMachine
{
    /**
     * Possible states are defined as const variables.
     */
    const ALIVE = 0;
    const ASLEEP = 1;
    const AWAKE = 2;
    const HUNGRY = 3;
    const THIRSTY = 4;
    const BORED = 5;
    const EATING = 6

    /**
     * Define public variables that you can use when
     * validating transition conditions.
     */
    public $sleepiness = 100;
    public $hunger = 0;

    /**
     * Override the `initial` method to return an array
     * of default states.
     */
    public static function initial()
    {
        return [Human::ALIVE, Human::ASLEEP, Human::BORED];
    }
}
```

### Creating an instance of a `StateMachine`

The machine defined above will be in three states when it is created. Consider this passing test:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_initialStates(): void
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
}
```

### Defining a `Transition` with a `Condition`

Transitions can be assigned to objects that extend StateMachine. Consider this passing test:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_addTransition(): void
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
}
```

Note the syntax passed to the `Condition` constructor above: `'[ALIVE]'`.
What this translates to is: "In the condition that one of my states is `Human::ALIVE`, 
transition OUT of state `Human::ASLEEP` and INTO state `Human::AWAKE`". Other states are preserved.
In order for this transition to be considered, `ASLEEP` must currently be one of our states.

You will notice two other unique syntax examples going forward:
 1. `{var}` === the value of the variable named `var`.
 1. `<var>` === a reference to the variable named `var`.

### `turn`ing a `StateMachine`

A machine needs to be `turn`ed. In other words, you need to call the `turn` method on
your object so that it will consider and validate transition conditions.

Expanding on the previous test:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_addTransition(): void
    {
        $human = new Human();

        $transition = $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]')
            )
        );

        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertTrue(!$human->is(Human::AWAKE));

        $human->turn();

        $this->assertTrue(!$human->is(Human::ASLEEP));
        $this->assertTrue($human->is(Human::AWAKE));
    }
}
```

When we called `turn`:

 1. All transition's (just one in this case) starting states (Human::ASLEEP in this case) are validated
    against the machine's current list of states.
 1. Since we are in the correct state for this transition to occur, we move onto validating its conditions.
 1. Because the only condition for this transition to occur is that one of our states is `Human::ALIVE`, the transition is valid
    and therefore is processed. `ASLEEP` is removed from our list of states and `AWAKE` is added.

### `PostTransition` operations

Let's say you want to modify the value of `sleepiness` after the transition defined above
is processed. Consider the following:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_postTransition(): void
    {
        $human = new Human();

        $transition = $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('[ALIVE]'),
                new PostTransition('<sleepiness> = 0')
            )
        );

        $this->assertEquals(100, $human->sleepiness);

        $human->turn();

        $this->assertEquals(0, $human->sleepiness);
    }
}
```

You can also pass a callable to `PostTransition`. For example:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_postTransitionCallback(): void
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
}
```

### Syntax: `{var}` and `<var>`

Remember, `<var>` reads as "a reference to the variable named `var`". So in the above example, 
`<sleepiness> = 0` is basically eval'd to `$this->sleepiness = 0` under the hood.

You might be asking yourself: "What's the point of that?". Good question! Those variables can also
be used in your `Condition` expressions. Consider the following:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_transitionCondition(): void
    {
        $human = new Human();

        $human->addTransition(
            new Transition(
                null,
                Human::HUNGRY,
                new Condition('{hunger} >= 50', '[ALIVE]')
            )
        );

        $this->assertTrue(0 === $human->hunger);
        $this->assertTrue(!$human->is(Human::HUNGRY));

        $human->hunger = 50;
        $human->turn();

        $this->assertTrue($human->is(Human::HUNGRY));
    }
}
```

First of all, take note of the `null` "from" state passed to `Transition`. This is valid,
and means that this transition's conditions will be considered no matter what state
we are currently in. Further, if the transition is valid and therefore processed,
no state is removed from the list of current states. We simply push a new state (HUNGRY)
onto the list of active states.

Next, note the string passed to the `Condition` constructor: `{hunger} >= 50`, which
reads as "the value of the `$hunger` variable is >= 50" or, by the time this condition is
evaluated, it reads: "50 >= 50" which, of course, is true.

Any number of expressions (as strings) can be passed to the `Condition` constructor.
The same goes for the `PostTransition` constructor. The expressions are processed in the
order which they are received.

### Alternative instantiation

You can use the `StateMachine`'s `new()` method to deep copy your `StateMachine`, which
means that all defined transitions, current state, current variable values are copied
along with it:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_deepCopy(): void
    {
        $human = new Human();
        $this->assertEquals(0, $human->hunger);

        $human->hunger = 25;

        $alice = $human->new();
        $this->assertEquals(25, $alice->hunger);

        $bob = $human->new();
        $this->assertEquals(25, $bob->hunger);

        $this->assertTrue(spl_object_hash($human) !== spl_object_hash($alice));
    }
}
```

This is just a convenience method for: `return unserialize(serialize($this));`

### `StateTick`ing

Using a `StateTick` you can define an operation expression that is eval'd after
an interval amount of time while a defined state is active. Consider the following test:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_stateTick(): void
    {
        $human = new Human();

        /**
         * Every 1 second, while we are ASLEEP, decrease sleepiness by 15
         */
        $human->tick(
            new StateTick(
                Human::ASLEEP,
                '<sleepiness> = {sleepiness} - 15',
                1
            )
        );

        $this->assertEquals(100, $human->sleepiness);

        sleep(1);
        $human->turn();

        $this->assertEquals(85, $human->sleepiness);
    }
}
```

The first time that `StateTick`'s expression is resolved,
it evaluates to (basically): `$this->sleepiness = 100 - 15`


Every time you run the example above, it will pass. That's because we're not
working with a database, and we're not saving the state of the object. `StateMachine` defines 
three methods that help solve this: `save`, `load` and `delete`. Making use of these methods,
you can preserve the state of your `StateMachine` through page refreshes. If you're using the CLI,
there's a better way to handle this, but we'll get into that later.

### Preserving StateMachine state through page loads

Consider the following test:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_statePreservation(): void
    {
        $human = new Human();

        $human->tick(
            new StateTick(
                Human::ASLEEP,
                '<sleepiness> = {sleepiness} - 15',
                1
            )
        );

        $this->assertEquals(100, $human->sleepiness);

        sleep(1);
        $human->turn();

        $this->assertEquals(85, $human->sleepiness);
        
        /**
         * Serialize this StateMachine and write it to disk.
         * Default location is __DIR__.'/.phpnfa/{filename}'.
         */
        $human->save('bob');

        /**
         * Let's pretend that this is a page refresh.
         */
        sleep(2);

        /**
         * Load the serialized object from disk.
         */
        $bob = StateMachine::load('bob');

        // We haven't turned this newly loaded machine, so StateTick hasn't been processed
        $this->assertEquals(85, $bob->sleepiness);
        $bob->turn();

        /**
         * Remember, two seconds have passed at this point since, so we
         * expect that sleepiness has been decreased by an amount of 30.
         */
        $this->assertEquals(55, $bob->sleepiness); /** * After another second passes, sleepiness will be equal to 40.
         * Let's create a transition that happens when that is true.
         */
        $bob->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('{sleepiness} <= 40')
            )
        );

        /**
         * Confirming we are not already awake.
         */
        $this->assertTrue(!$bob->is(Human::AWAKE));
        
        /**
         * Sleep for another second, again pretending that this is a page
         * load.
         */
        sleep(1);

        /**
         * Don't forget to turn the machine. Usually you will just call
         * this on every page load. Only in contrived examples such as this
         * will you call it many times in a row.
         */
        $bob->turn();

        $this->assertEquals(40, $bob->sleepiness);
        $this->assertTrue($bob->is(Human::AWAKE));
        $this->assertTrue(!$bob->is(Human::ASLEEP));
    }
}
```

Alternatively, if you're using PHP in your CLI, you should use `StateMachine`'s `activate` method:

```php
final class HumanTest extends PHPUnit\Framework\TestCase
{
    public function test_activate(): void
    {
        $human = new Human();

        /**
         * While ASLEEP is one of our states,
         * decrease $this->sleepiness by 15 every 1 second
         */
        $human->tick(
            new StateTick(
                Human::ASLEEP,
                '<sleepiness> = {sleepiness} - 50',
                1
            )
        );

        /**
         * When sleepiness is <= 0, and we are ASLEEP, wake up.
         */
        $human->addTransition(
            new Transition(
                Human::ASLEEP,
                Human::AWAKE,
                new Condition('{sleepiness} <= 0')
            )
        );

        $this->assertTrue($human->is(Human::ASLEEP));
        $this->assertEquals(100, $human->sleepiness);

        /**
         * "activate" the machine.
         * This essentially `turn`'s the machine every 1 second (StateMachine@tickRate),
         * until the state passed as the argument is no longer one of our active states.
         */
        // turn until !$human->is(Human::ASLEEP); ... 2 seconds in this case
        $human->activate(Human::ASLEEP); 

        $this->assertTrue(!$human->is(Human::ASLEEP));
        $this->assertEquals(0, $human->sleepiness);
    }
}
```
