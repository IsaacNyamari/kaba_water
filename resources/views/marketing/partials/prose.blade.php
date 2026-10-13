{{-- Typographic container for long-form legal pages. Use with @component. --}}
<div class="mx-auto max-w-3xl px-5 py-16 text-base leading-7 text-zinc-600 sm:px-8 lg:py-20 dark:text-zinc-300
    [&_a]:font-medium [&_a]:text-teal [&_a]:underline [&_a]:underline-offset-4 dark:[&_a]:text-light-aqua
    [&_h2]:mt-12 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-navy dark:[&_h2]:text-white
    [&_li]:mt-2 [&_p]:mt-4 [&_strong]:font-semibold [&_strong]:text-navy dark:[&_strong]:text-white
    [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:marker:text-aqua">
    {{ $slot }}
</div>
