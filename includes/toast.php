<?php
$toast_type = '';
$toast_title = '';
$toast_message = '';

if (isset($_GET['success'])) {
    $toast_type = 'success';
    $toast_title = 'Success!';
    $toast_message = htmlspecialchars($_GET['success']);
} elseif (isset($_GET['error'])) {
    $toast_type = 'error';
    $toast_title = 'Error!';
    $toast_message = htmlspecialchars($_GET['error']);
}
?>
<?php if ($toast_type): ?>
<div id="toast" class="fixed top-4 right-4 z-[100] flex items-start gap-3 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-gray-200 dark:border-slate-700 px-4 py-3 min-w-[320px] max-w-md" data-type="<?= $toast_type ?>">
    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mt-0.5
        <?= $toast_type === 'success' ? 'bg-green-100 dark:bg-green-500/15 text-green-600 dark:text-green-400' : 'bg-red-100 dark:bg-red-500/15 text-red-600 dark:text-red-400' ?>">
        <?php if ($toast_type === 'success'): ?>
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <?php else: ?>
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <?php endif; ?>
    </div>
    <div class="flex-1">
        <p class="text-sm font-semibold text-gray-800 dark:text-slate-200"><?= $toast_title ?></p>
        <p class="text-xs text-gray-600 dark:text-slate-400 mt-0.5"><?= $toast_message ?></p>
    </div>
    <button onclick="closeToast(this)" class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
<?php endif; ?>
