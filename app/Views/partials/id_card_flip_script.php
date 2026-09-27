<script>
/**
 * Click / keyboard flip for the student ID card preview.
 *
 * The BACK face is always present in the DOM (it is flipped into view with a
 * 3D transform), so printing needs no JavaScript: @media print in
 * partials/id_card_style.php demotes the flip to a plain side-by-side row.
 */
(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var flip = document.getElementById('idc-flip');
        if (!flip) {
            return;
        }

        var hint = document.getElementById('idc-hint');

        function render(isBack) {
            flip.classList.toggle('is-flipped', isBack);
            flip.setAttribute('aria-pressed', isBack ? 'true' : 'false');
            flip.setAttribute('aria-label', isBack
                ? 'Show the front of this ID card'
                : 'Show the back of this ID card');
            if (hint) {
                hint.textContent = isBack
                    ? 'Showing the BACK — click the card to flip it back to the front.'
                    : 'Showing the FRONT — click the card to flip it over.';
            }
        }

        flip.addEventListener('click', function () {
            render(!flip.classList.contains('is-flipped'));
        });

        flip.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                flip.click();
            }
        });

        render(false);
    });
})();
</script>
