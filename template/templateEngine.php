<?php

namespace Puchiko\template;

/**
 * A lightweight, performant template engine for parsing and rendering HTML templates.
 *
 * Supports:
 * - Variable binding with {$variableName} syntax
 * - Array/property access with {$array.key} or {$object->property} syntax
 * - Template inheritance via {template:filename.tpl} includes
 * - Conditional blocks: {if:variable}{else}{/if}
 * - Loop blocks: {foreach:array as item}{/foreach}
 * - Automatic HTML entity escaping for security
 *
 * Usage:
 *   $engine = new TemplateEngine('source/templates');
 *   $engine->bind(['SITE_NAME' => 'My Site', 'BODY' => '<p>Hello</p>']);
 *   echo $engine->render('base.tpl');
 */
class templateEngine
{
    /**
     * Default regex pattern for variable placeholders: {$VARIABLE_NAME}
     */
    private const PATTERN_VARIABLE = '/\{\$([a-zA-Z_][a-zA-Z0-9_.\-]*)\}/';

    /**
     * Pattern for template includes: {template:filename.tpl}
     */
    private const PATTERN_INCLUDE = '/\{template:([^}]+)\}/';

    /**
     * Pattern for conditional blocks: {if:variable}{content}{/if}
     */
    private const PATTERN_IF = '/\{if:([^}]+)\}([\s\S]*?)\{\/if\}/';

    /**
     * Pattern for else within conditionals: {else}
     */
    private const PATTERN_ELSE = '/\{else\}/';

    /**
     * Pattern for foreach loops: {foreach:variable as alias}{content}{/foreach}
     */
    private const PATTERN_FOREACH = '/\{foreach:([a-zA-Z_][a-zA-Z0-9_.\-]*)\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*)\}([\s\S]*?)\{\/foreach\}/';

    /**
     * Directory path where template files are stored.
     */
    private string $templateDir;

    /**
     * Cache for parsed template content to avoid repeated file reads.
     * @var array<string, string>
     */
    private array $templateCache = [];

    /**
     * Data bound to the template for variable resolution.
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Whether to automatically escape HTML entities for security.
     */
    private bool $autoEscape = true;

    /**
     * Stack of nested templates for include context.
     * @var array<int, string>
     */
    private array $includeStack = [];

    /**
     * Constructor.
     *
     * @param string $templateDir Path to the directory containing .tpl files.
     * @param bool $autoEscape Enable automatic HTML entity escaping (default: true).
     */
    public function __construct(string $templateDir, bool $autoEscape = true)
    {
        $this->templateDir = rtrim($templateDir, DIRECTORY_SEPARATOR);
        $this->autoEscape = $autoEscape;

        if (!is_dir($this->templateDir)) {
            throw new \InvalidArgumentException(
                sprintf('Template directory does not exist: %s', $this->templateDir)
            );
        }
    }

    /**
     * Bind an array of data to the template engine.
     *
     * Each key-value pair becomes available as a template variable.
     * Nested arrays and objects can be accessed using dot notation
     * (e.g., {$user.name}).
     *
     * @param array<string, mixed> $data Associative array of variables.
     * @return self For method chaining.
     */
    public function bind(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Bind a single variable to the template engine.
     *
     * @param string $name Variable name (without {$ } wrapper).
     * @param mixed $value Variable value.
     * @return self For method chaining.
     */
    public function bindVariable(string $name, mixed $value): self
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * Set whether HTML entities should be automatically escaped.
     *
     * @param bool $enabled True to enable escaping, false to disable.
     * @return self For method chaining.
     */
    public function setAutoEscape(bool $enabled): self
    {
        $this->autoEscape = $enabled;
        return $this;
    }

    /**
     * Clear all bound data and the template cache.
     *
     * @return self For method chaining.
     */
    public function clear(): self
    {
        $this->data = [];
        $this->templateCache = [];
        $this->includeStack = [];
        return $this;
    }

    /**
     * Parse and render a template file with the bound data.
     *
     * This is the primary method for generating HTML output.
     * It handles variable substitution, includes, conditionals, and loops.
     *
     * @param string $templateName Name of the .tpl file (without extension).
     * @param array<string, mixed>|null $additionalData Optional additional data to merge.
     * @return string Rendered HTML string.
     * @throws TemplateFileNotFoundException If the template file does not exist.
     * @throws UndefinedVariableException If an undefined variable is referenced.
     * @throws TemplateParseException If template syntax cannot be parsed.
     */
    public function render(string $templateName, ?array $additionalData = null): string
    {
        if ($additionalData !== null) {
            $this->bind($additionalData);
        }

        $templateContent = $this->loadTemplate($templateName);

        try {
            $rendered = $this->processTemplate($templateContent, $this->data);
        } finally {
            if ($additionalData !== null) {
                foreach ($additionalData as $key => $value) {
                    unset($this->data[$key]);
                }
            }
        }

        return $rendered;
    }

    /**
     * Render a raw template string (not from a file).
     *
     * Useful for inline templates or programmatic template content.
     *
     * @param string $templateContent Raw template string.
     * @param array<string, mixed>|null $data Optional data override.
     * @return string Rendered HTML string.
     */
    public function renderString(string $templateContent, ?array $data = null): string
    {
        $originalData = $this->data;

        if ($data !== null) {
            $this->data = array_merge($this->data, $data);
        }

        try {
            return $this->processTemplate($templateContent, $this->data);
        } finally {
            $this->data = $originalData;
        }
    }

    /**
     * Load a template file by name.
     *
     * Uses an internal cache to avoid repeated file I/O operations.
     *
     * @param string $templateName Name of the .tpl file (without extension).
     * @return string Contents of the template file.
     * @throws TemplateFileNotFoundException If the file does not exist.
     */
    private function loadTemplate(string $templateName): string
    {
        $cacheKey = $this->templateDir . DIRECTORY_SEPARATOR . $templateName;

        if (isset($this->templateCache[$cacheKey])) {
            return $this->templateCache[$cacheKey];
        }

        $filePath = $this->templateDir . DIRECTORY_SEPARATOR . ($templateName . '.tpl');

        if (!file_exists($filePath)) {
            throw new TemplateFileNotFoundException(
                sprintf('Template file not found: %s', $filePath)
            );
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new TemplateParseException(
                sprintf('Failed to read template file: %s', $filePath)
            );
        }

        $this->templateCache[$cacheKey] = $content;
        return $content;
    }

    /**
     * Process a template string with the given data context.
     *
     * Handles includes, loops, conditionals, and variable substitution
     * in the correct order to support nested templates.
     *
     * @param string $templateContent Raw template content.
     * @param array<string, mixed> $context Data context for variable resolution.
     * @return string Processed template output.
     * @throws TemplateParseException If parsing fails.
     */
    private function processTemplate(string $templateContent, array $context): string
    {
        $result = $templateContent;

        // 1. Process foreach loops first (they may contain nested structures)
        $result = $this->processForeachLoops($result, $context);

        // 2. Process conditional blocks
        $result = $this->processConditionals($result, $context);

        // 3. Process template includes
        $result = $this->processIncludes($result, $context);

        // 4. Resolve all remaining variables
        $result = $this->resolveVariables($result, $context);

        return $result;
    }

    /**
     * Process {foreach} loop blocks in the template.
     *
     * @param string $content Template content.
     * @param array<string, mixed> $context Data context.
     * @return string Content with loops expanded.
     * @throws TemplateParseException If loop variable is undefined.
     */
    private function processForeachLoops(string $content, array $context): string
    {
        $callback = function (array $matches) use ($context): string {
            $arrayName = $matches[1];
            $alias = $matches[2];
            $loopBody = $matches[3];

            if (!array_key_exists($arrayName, $context) || !is_iterable($context[$arrayName])) {
                throw new TemplateParseException(
                    sprintf('Foreach variable "%s" is not iterable', $arrayName)
                );
            }

            $output = '';
            foreach ($context[$arrayName] as $item) {
                $loopContext = array_merge($context, [$alias => $item]);
                $output .= str_replace('${' . $alias . '}', '$' . '{' . $alias . '}', $this->processTemplate($loopBody, $loopContext));
            }

            return $output;
        };

        return preg_replace_callback(self::PATTERN_FOREACH, $callback, $content) ?? $content;
    }

    /**
     * Process {if}{/if} conditional blocks in the template.
     *
     * @param string $content Template content.
     * @param array<string, mixed> $context Data context.
     * @return string Content with conditionals resolved.
     * @throws TemplateParseException If conditional variable is undefined.
     */
    private function processConditionals(string $content, array $context): string
    {
        $callback = function (array $matches) use ($context): string {
            $condition = trim($matches[1]);
            $trueContent = $matches[2];

            // Check for {else} block
            if (preg_match(self::PATTERN_ELSE, $trueContent, $elseMatches)) {
                $parts = preg_split(self::PATTERN_ELSE, $trueContent, 2);
                $trueContent = $parts[0] ?? '';
                $falseContent = $parts[1] ?? '';
            } else {
                $falseContent = '';
            }

            // Evaluate condition
            $isTrue = false;
            if (array_key_exists($condition, $context)) {
                $isTrue = (bool) $context[$condition];
            } elseif (preg_match('/^!(.+)$/', $condition, $negateMatches)) {
                $negatedVar = trim($negateMatches[1]);
                if (array_key_exists($negatedVar, $context)) {
                    $isTrue = !(bool) $context[$negatedVar];
                }
            }

            return $isTrue ? $trueContent : $falseContent;
        };

        return preg_replace_callback(self::PATTERN_IF, $callback, $content) ?? $content;
    }

    /**
     * Process {template:filename.tpl} include directives.
     *
     * Recursively loads and merges included template content.
     *
     * @param string $content Template content.
     * @param array<string, mixed> $context Data context.
     * @return string Content with includes resolved.
     * @throws TemplateParseException If include depth is exceeded.
     * @throws TemplateFileNotFoundException If included file not found.
     */
    private function processIncludes(string $content, array $context): string
    {
        // Prevent infinite recursion
        if (count($this->includeStack) > 20) {
            throw new TemplateParseException('Maximum include depth exceeded');
        }

        $callback = function (array $matches) use ($context): string {
            $includeFile = trim($matches[1]);

            $this->includeStack[] = $includeFile;

            try {
                $includeContent = $this->loadTemplate($includeFile);
                return $this->processTemplate($includeContent, $context);
            } finally {
                array_pop($this->includeStack);
            }
        };

        return preg_replace_callback(self::PATTERN_INCLUDE, $callback, $content) ?? $content;
    }

    /**
     * Resolve all {$VARIABLE} placeholders in the template content.
     *
     * Each variable is looked up in the data context using dot notation
     * for nested access (e.g., {$user.name} accesses $context['user']['name']).
     * Values are HTML-escaped by default for security.
     *
     * @param string $content Template content with placeholders.
     * @param array<string, mixed> $context Data context.
     * @return string Content with all variables resolved.
     * @throws UndefinedVariableException If a variable is not found in context.
     */
    private function resolveVariables(string $content, array $context): string
    {
        $callback = function (array $matches) use ($context): string {
            $varPath = $matches[1];

            $value = $this->resolveVariablePath($varPath, $context);

            if ($value === null) {
                throw new UndefinedVariableException(
                    sprintf('Undefined template variable: %s', $varPath)
                );
            }

            if (is_bool($value)) {
                return $value ? '1' : '';
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if (is_array($value) || is_object($value)) {
                return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
            }

            if ($this->autoEscape) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }

            return (string) $value;
        };

        return preg_replace_callback(self::PATTERN_VARIABLE, $callback, $content) ?? $content;
    }

    /**
     * Resolve a variable path supporting dot notation for nested access.
     *
     * Supports:
     * - Simple: {$name} -> $context['name']
     * - Nested array: {$user.name} -> $context['user']['name']
     * - Object property: {$user->name} -> $context['user']->name
     * - Mixed: {$user.settings.theme} -> $context['user']['settings']['theme']
     *
     * @param string $path Variable path (e.g., "user.name").
     * @param array<string, mixed> $context Root data context.
     * @return mixed|null The resolved value, or null if not found.
     */
    private function resolveVariablePath(string $path, array $context): mixed
    {
        $segments = preg_split('/[.\-]/', $path);

        if ($segments === false || empty($segments)) {
            return null;
        }

        $current = $context;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
            } elseif ($current instanceof \stdClass || is_object($current)) {
                if (!property_exists($current, $segment)) {
                    return null;
                }
                $current = $current->$segment;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Get the list of available template files in the template directory.
     *
     * @return array<int, string> List of template names (without extension).
     */
    public function getAvailableTemplates(): array
    {
        $files = glob($this->templateDir . '/*.tpl');

        if ($files === false) {
            return [];
        }

        return array_map(function (string $file): string {
            return basename($file, '.tpl');
        }, $files);
    }

    /**
     * Clear the internal template cache.
     *
     * Useful when templates may have been modified externally.
     *
     * @return self For method chaining.
     */
    public function clearCache(): self
    {
        $this->templateCache = [];
        return $this;
    }

    /**
     * Get the template directory path.
     *
     * @return string The configured template directory.
     */
    public function getTemplateDir(): string
    {
        return $this->templateDir;
    }
}
