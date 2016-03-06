window.addEventListener('load', function() {
    //UI elements
    var bg = document.getElementById('bg');
    var bghex = document.getElementById('bghex');
    var color = document.getElementById('color');
    var colorhex = document.getElementById('colorhex');
    var link = document.getElementById('link');
    var linkhex = document.getElementById('linkhex');
    var font = document.getElementById('font');
    var nobg = document.getElementById('nobg');
    
    [bg, color, link, font].forEach(function(x){x.onchange = updateStyle;});
    [bghex, colorhex, linkhex].forEach(function(x){x.onchange = updateHex;});
    
    nobg.onchange = function(){
        if (bg.hasAttribute('disabled')) {
            bg.removeAttribute('disabled');
            bghex.removeAttribute('disabled');
        }else{
            bg.setAttribute('disabled', true);
            bghex.setAttribute('disabled', false);
        }
        updateStyle();
    };
    
    var serviceCode = document.getElementById('serviceCode');
    var userId = document.getElementById('uid').value;
    //The preview DIV
    var previewDiv = document.getElementById('preview');
    
    //a function to change all style settings
    function updateStyle() {
        if (!bg.hasAttribute('disabled'))
            previewDiv.style.backgroundColor = bghex.value = bg.value;
        else previewDiv.style.backgroundColor = null;
        previewDiv.style.color = colorhex.value = color.value;
        previewDiv.style.fontFamily = font.value;
        linkhex.value = link.value;
        var links = previewDiv.getElementsByClassName('previewLink');
        for (var i=0; i<links.length; i++) {
            links[i].style.color = link.value;
            links[i].style.textDecoration = 'underline';
        }
        //out put the code
        var code = '<div id="jiaDiv"></div><script type="application/javascript">function jia_callBack(j){jiaDiv.innerHTML=j;}</script><script src="http://jazzinaustin.com/service/service.php?id=';
        code += userId;
        if (!bg.hasAttribute('disabled'))
            code += '&bg=' + encodeURIComponent(bg.value);
        else code += '&bg=none';
        code += '&color=' + encodeURIComponent(color.value);
        code += '&link=' + encodeURIComponent(link.value);
        code += '&font=' + encodeURIComponent(font.value);
        code += '"></script>';
        serviceCode.value = code;
    }
    updateStyle();
    
    //update style via hex input
    function updateHex() {
        bg.value = bghex.value;
        color.value = colorhex.value;
        link.value = linkhex.value;
        updateStyle();
    }
});