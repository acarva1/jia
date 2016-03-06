window.addEventListener('load', function() {
    var nav = document.querySelector('.nav');
    var toggle = document.getElementById('menuToggle');
    toggle.onclick = function() {
        if (nav.classList.contains('menuVisibility')) {
            nav.classList.remove('menuVisibility');
        }else nav.classList.add('menuVisibility');
    }
});