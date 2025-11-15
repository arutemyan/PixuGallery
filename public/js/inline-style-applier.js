// Simple applier: apply `data-bg` and `data-color` on load only.
(function(){
    'use strict';

    function applyBgColor(root) {
        root = root || document;
        root.querySelectorAll('[data-bg], [data-color]').forEach(function(el){
            try{
                var bg = el.getAttribute('data-bg');
                var color = el.getAttribute('data-color');
                if(bg){ el.style.backgroundColor = bg; }
                if(color){ el.style.color = color; }
            }catch(e){ /* ignore */ }
        });
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', function(){ applyBgColor(document); });
    } else {
        applyBgColor(document);
    }

})();
