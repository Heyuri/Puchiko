<?php

namespace Puchiko\background;

/**
 * Implement this on any class you want to run as a background task.
 *
 * The constructor must be callable with no arguments; wire all dependencies
 * manually inside handle() using the same pattern as moduleAdmin::initialize().
 */
interface BackgroundTaskInterface {
	/**
	 * Execute the task.
	 *
	 * @param array<string, mixed> $args JSON-serializable arguments passed at dispatch time.
	 */
	public function handle(array $args): void;
}
