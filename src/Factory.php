<?php

declare(strict_types=1);

/**
 * This file is part of NexusPHP CS Config.
 *
 * (c) 2020 John Paul E. Balandan, CPA <paulbalandan@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Nexus\CsConfig;

use Nexus\CsConfig\Ruleset\RulesetInterface;
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/**
 * The Factory class is invoked on each project's `.php_cs` to create
 * the specific ruleset for the project.
 *
 * @internal
 */
final class Factory
{
    /**
     * Current RulesetInterface instance.
     *
     * @var \Nexus\CsConfig\Ruleset\RulesetInterface
     */
    private $ruleset;

    /**
     * Array of resolved options.
     *
     * @var array<string, mixed>
     */
    private $options = [];

    /**
     * Constructor.
     *
     * @param \Nexus\CsConfig\Ruleset\RulesetInterface $ruleset
     * @param array<string, mixed>                     $options
     */
    private function __construct(RulesetInterface $ruleset, array $options)
    {
        $this->ruleset = $ruleset;
        $this->options = $options;
    }

    /**
     * Prepares the ruleset and options before the `PhpCsFixer\Config` object
     * is created.
     *
     * @param \Nexus\CsConfig\Ruleset\RulesetInterface $ruleset
     * @param array<string, mixed>                     $overrides
     * @param array<string, mixed>                     $options
     *
     * @return self
     */
    public static function create(RulesetInterface $ruleset, array $overrides = [], array $options = []): self
    {
        if (\PHP_VERSION_ID < $ruleset->getRequiredPHPVersion()) {
            throw new \RuntimeException(sprintf(
                'The "%s" ruleset requires a minimum PHP_VERSION_ID of "%d" but current PHP_VERSION_ID is "%d".',
                $ruleset->getName(),
                $ruleset->getRequiredPHPVersion(),
                \PHP_VERSION_ID
            ));
        }

        // Meant to be used in vendor/ to get to the root directory
        $dir = \dirname(__DIR__, 4);
        $dir = realpath($dir) ?: $dir;

        $defaultFinder = Finder::create()
            ->files()
            ->in([$dir])
            ->exclude(['build'])
        ;

        // Resolve Config options
        $options['cacheFile'] = $options['cacheFile'] ?? '.php_cs.cache';
        $options['customFixers'] = $options['customFixers'] ?? [];
        $options['finder'] = $options['finder'] ?? $defaultFinder;
        $options['format'] = $options['format'] ?? 'txt';
        $options['hideProgress'] = $options['hideProgress'] ?? false;
        $options['indent'] = $options['indent'] ?? '    ';
        $options['lineEnding'] = $options['lineEnding'] ?? "\n";
        $options['phpExecutable'] = $options['phpExecutable'] ?? null;
        $options['isRiskyAllowed'] = $options['isRiskyAllowed'] ?? ($ruleset->willAutoActivateIsRiskyAllowed() ?: false);
        $options['usingCache'] = $options['usingCache'] ?? true;
        $options['rules'] = array_merge($ruleset->getRules(), $overrides, $options['customRules'] ?? []);

        return new self($ruleset, $options);
    }

    /**
     * Creates a `PhpCsFixer\Config` object that is applicable for libraries,
     * i.e., has their own header docblock in place.
     *
     * @param string   $library
     * @param string   $author
     * @param string   $email
     * @param null|int $startingYear
     *
     * @return \PhpCsFixer\Config
     */
    public function forLibrary(string $library, string $author, string $email = '', ?int $startingYear = null)
    {
        $year = (string) $startingYear;

        if ('' !== $year) {
            $year .= ' ';
        }

        if ('' !== $email) {
            $email = trim($email, '<>');
            $email = ' <' . $email . '>';
        }

        $header = sprintf(
            '
This file is part of %s.

(c) %s%s%s

For the full copyright and license information, please view
the LICENSE file that was distributed with this source code.
            ',
            $library,
            $year,
            $author,
            $email
        );

        return $this->invoke([
            'header_comment' => [
                'header'       => trim($header),
                'comment_type' => 'PHPDoc',
            ],
        ]);
    }

    public function forProjects(): Config
    {
        return $this->invoke();
    }

    /**
     * The main method of creating the Config instance.
     *
     * @param array<string, array<string>|bool> $overrides
     *
     * @return \PhpCsFixer\Config
     *
     * @internal
     */
    private function invoke(array $overrides = []): Config
    {
        $rules = array_merge($this->options['rules'], $overrides);

        return (new Config($this->ruleset->getName()))
            ->registerCustomFixers($this->options['customFixers'])
            ->setCacheFile($this->options['cacheFile'])
            ->setFinder($this->options['finder'])
            ->setFormat($this->options['format'])
            ->setHideProgress($this->options['hideProgress'])
            ->setIndent($this->options['indent'])
            ->setLineEnding($this->options['lineEnding'])
            ->setPhpExecutable($this->options['phpExecutable'])
            ->setRiskyAllowed($this->options['isRiskyAllowed'])
            ->setUsingCache($this->options['usingCache'])
            ->setRules($rules)
        ;
    }
}
