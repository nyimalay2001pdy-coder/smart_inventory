<!-- Unsaved Changes Modal -->
<div id="unsavedModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/40" onclick="hideUnsavedModal()"></div>
    <div class="flex items-center justify-center min-h-full p-4">
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden" style="animation: modalIn 0.2s ease-out;">
            <div class="p-6 text-center">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Unsaved Changes</h3>
                <p class="text-sm text-gray-500 mb-6">You have unsaved changes. Are you sure you want to leave this page?</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="hideUnsavedModal()" class="px-5 py-2.5 bg-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-300 transition text-sm">Stay on Page</button>
                    <button onclick="confirmLeavePage()" class="px-5 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition text-sm">Leave Page</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var forms = document.querySelectorAll('form[data-form-guard="true"]');
    if (forms.length === 0) return;

    var formDirty = false;
    var guardHandlers = [];

    function markDirty() {
        formDirty = true;
    }

    function resetDirty() {
        formDirty = false;
    }

    forms.forEach(function(form) {
        form.querySelectorAll('input, textarea, select').forEach(function(el) {
            el.addEventListener('input', markDirty);
            el.addEventListener('change', markDirty);
        });
        form.addEventListener('submit', resetDirty);
    });

    document.addEventListener('click', function(e) {
        if (!formDirty) return;
        var link = e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href) return;
        if (href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;
        if (link.getAttribute('target') === '_blank') return;
        if (link.hasAttribute('download')) return;
        if (href.indexOf('logout.php') !== -1) return;
        e.preventDefault();
        showUnsavedModal(function() {
            window.location.href = href;
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (formDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    window.resetFormGuard = resetDirty;
})();
</script>
