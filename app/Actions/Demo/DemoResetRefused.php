<?php

namespace App\Actions\Demo;

use RuntimeException;

/**
 * Plan 027: the reset found something it will not write over and changed nothing. The message is a
 * short code with the tables concerned ("dependents|projects|reports"), which the administration page
 * turns into a sentence in the reader's language.
 */
class DemoResetRefused extends RuntimeException {}
