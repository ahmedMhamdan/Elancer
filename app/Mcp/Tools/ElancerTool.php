<?php

namespace App\Mcp\Tools;

use App\Mcp\AgentAccess;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Plan 033: what every Elancer tool shares. A tool is listed only to a token that may use it; it answers
 * with structured data; and a refusal from the rules the pages use keeps its reason instead of becoming
 * a general server error. Validation errors are left to laravel/mcp, which reports each field.
 */
abstract class ElancerTool extends Tool
{
    public const PER_PAGE = 20;

    public const MOST_PER_PAGE = 50;

    /** Changes a minute per token, as the API allows (plan 028). */
    public const WRITES_PER_MINUTE = 20;

    /** The API token ability this tool needs; null when any token may call it. */
    protected ?string $ability = null;

    /** Whether the tool changes something, and so counts toward the write limit. */
    protected bool $writes = false;

    public function shouldRegister(Request $request): bool
    {
        return AgentAccess::allows($request->user(), $this->ability);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        if (! $user instanceof User || ! AgentAccess::allows($user, $this->ability)) {
            return Response::error(__('This connection may not use this tool.'));
        }
        if ($this->writes) {
            $key = 'mcp-write:'.AgentAccess::key($user);
            if (RateLimiter::tooManyAttempts($key, self::WRITES_PER_MINUTE)) {
                return Response::error(__('Too many changes in a minute. Try again shortly.'));
            }
            RateLimiter::hit($key, 60);
        }
        try {
            return Response::structured($this->answer($request, $user));
        } catch (HttpExceptionInterface $exception) {
            return Response::error($exception->getMessage() !== '' ? $exception->getMessage() : self::reason($exception->getStatusCode()));
        } catch (ModelNotFoundException) {
            return Response::error(self::reason(404));
        }
    }

    /**
     * The tool's answer, built from the fields the matching page or API route already gives.
     *
     * @return array<string, mixed>
     */
    abstract protected function answer(Request $request, User $user): array;

    /** The HTTP request the call came in on, for the API resources that render a record. */
    protected function http(): HttpRequest
    {
        return request();
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    protected function page(LengthAwarePaginator $page, array $items): array
    {
        return ['data' => $items, 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()];
    }

    /** @return array{page: list<mixed>, per_page: list<mixed>} */
    protected static function paging(): array
    {
        return ['page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MOST_PER_PAGE]];
    }

    private static function reason(int $status): string
    {
        return match ($status) {
            401, 403 => __('This account may not do that.'),
            404 => __('Not found, or not visible to this account.'),
            409 => __('This changed in the meantime. Read it again before trying.'),
            429 => __('Too many requests. Try again shortly.'),
            default => __('That could not be done.'),
        };
    }
}
