{{--
    فوتر الحقوق — مطلب صريح في docs/06 بند ٧.

    النص بييجي من إعدادات المظهر (قابل للدهس لكل مستأجر) ومترجم،
    فمفيش نص مكتوب هنا. لو فاضي، الفوتر مابيظهرش خالص.
--}}
@if (filled($text))
    <footer class="fi-footer px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
        {{ $text }}
    </footer>
@endif
