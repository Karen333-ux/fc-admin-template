<?php

declare(strict_types=1);

namespace Src\Support\Domain\Exceptions;

use RuntimeException;

/**
 * جذر كل استثناءات الـ Domain.
 *
 * رسائل الاستثناءات دي **للمطوّر** — مش بتتعرض للمستخدم، فمش من __().
 * أي استثناء بيوصل للواجهة لازم يكون مترجم (docs/18 بند ٨).
 */
abstract class DomainException extends RuntimeException {}
