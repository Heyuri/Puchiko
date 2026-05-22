<?php

namespace Puchiko\background;

/**
 * Maps human-readable names to the class that implements them.
 *
 * Register tasks early in the bootstrap (or in a module's initialize()) before
 * any dispatch call.
 *
 * For classes that live outside the autoloader's search path (e.g. module files
 * under module/), pass the absolute path to their file via $file so the runner
 * can require it before instantiating.
 *
 * Example (autoloaded class):
 *   BackgroundTaskRegistry::register('my_task', MyTask::class);
 *
 * Example (non-autoloaded module class):
 *   BackgroundTaskRegistry::register('anonymize_ips', AnonIpTask::class, __DIR__ . '/anonIpTask.php');
 */
class BackgroundTaskRegistry {
	/** @var array<string, array{class: string, file: string|null}> */
	private static array $tasks = [];

	/**
	 * Register a task class under a unique name.
	 *
	 * @param string      $name   Unique task identifier.
	 * @param string      $class  FQN implementing BackgroundTaskInterface.
	 * @param string|null $file   Absolute path to the file to require before
	 *                            instantiating the class (for non-autoloaded classes).
	 *
	 * @throws \InvalidArgumentException If the class is autoloaded but does not
	 *                                   implement BackgroundTaskInterface, or if
	 *                                   $file is provided but does not exist.
	 */
	public static function register(string $name, string $class, ?string $file = null): void {
		if ($file !== null) {
			if (!is_file($file)) {
				throw new \InvalidArgumentException(
					"Task file '$file' does not exist."
				);
			}
		} elseif (!is_a($class, BackgroundTaskInterface::class, true)) {
			throw new \InvalidArgumentException(
				"$class must implement " . BackgroundTaskInterface::class . '.'
			);
		}

		self::$tasks[$name] = ['class' => $class, 'file' => $file];
	}

	/**
	 * Resolve a registered name to its class + optional file entry.
	 *
	 * @return array{class: string, file: string|null}
	 * @throws \InvalidArgumentException If the name has not been registered.
	 */
	public static function resolve(string $name): array {
		if (!array_key_exists($name, self::$tasks)) {
			throw new \InvalidArgumentException(
				"No background task registered under '$name'."
			);
		}

		return self::$tasks[$name];
	}

	/** @return array<string, array{class: string, file: string|null}> */
	public static function all(): array {
		return self::$tasks;
	}
}
