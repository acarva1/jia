//cause cancel button to make the browser go back one page.
window.addEventListener('load', function() {
    var cancelButton = document.getElementById('cancelButton');
    cancelButton.onclick = function() {
        //go back
        history.back();
    }
});