<x-base>
    <style>
        ::selection {
            background-color: #3910b5;
            color: #fff;
        }
    </style>
    <main class="p-4 flex flex-col gap-4 text-neutral-900 justify-center items-center h-screen">
        <h1 class="text-5xl font-bold">Tempest PHP</h1>
        <p>
            This is the template project for Tempest PHP. You can edit this file to create your own homepage.
        </p>
        <p>
            PHP version: <strong><?= phpversion() ?></strong><br>
        </p>
    </main>
</x-base>