<?php

namespace Pest {
    class Configuration
    {
        public function extend(string $class): self
        {
            return $this;
        }

        public function use(string $class): self
        {
            return $this;
        }

        public function in(string $directory): void {}
    }

    class Expectation
    {
        public function extend(string $name, callable $extend): void {}

        public function toBe(mixed $expected): mixed
        {
            return $expected;
        }
    }

    function pest(): Configuration
    {
        return new Configuration();
    }

    function expect(mixed $value = null): Expectation
    {
        return new Expectation();
    }
}

namespace PHPUnit\Framework {
    class SkippedTestError extends \RuntimeException {}
}
