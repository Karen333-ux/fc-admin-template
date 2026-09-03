<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * بيربط كل سطر لوج في الطلب بمعرّف واحد. (docs/11 بند ٢)
 *
 * ⚠️ المعرّف بيتولّد **مرة واحدة** للطلب كله وبيتشارك عبر
 *    `Log::shareContext()`. أي حل بيولّد معرّف عند كل سطر بيدّي سطور
 *    مالهاش أي قيمة للتتبّع.
 *
 * ⚠️ المعرّف الجاي من الترويسة بيتقبل **بشرط الشكل** — مش على عماه.
 *    القيمة دي بتوصل للوج والاستجابة، فنص عشوائي من العميل معناه حقن
 *    في اللوج (سطر جديد، رموز تحكّم) أو استجابة ملوّثة.
 *
 * المستأجر والمستخدم **مش** هنا بالقصد: بيتحطّوا في `ContextProcessor`
 * لأنهم بيتحدّدوا بعد الميدلوير ده. (شوف تعليق المعالج)
 */
final class AssignRequestContext
{
    /** حد أقصى معقول لمعرّف جاي من بره */
    private const MAX_INCOMING_LENGTH = 128;

    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);

        Log::shareContext([
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->header(self::HEADER);

        if (is_string($incoming) && $this->isSafe($incoming)) {
            return $incoming;
        }

        return (string) Str::uuid();
    }

    /** حروف وأرقام وشرطات بس — بيمنع حقن سطور أو رموز تحكّم في اللوج */
    private function isSafe(string $candidate): bool
    {
        return $candidate !== ''
            && mb_strlen($candidate) <= self::MAX_INCOMING_LENGTH
            && preg_match('/^[A-Za-z0-9._-]+$/', $candidate) === 1;
    }
}
