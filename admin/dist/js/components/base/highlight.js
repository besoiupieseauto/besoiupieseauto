(()=>{$(".highlight").each(function(){let h=hljs.highlight(jsBeautify.html($(this).html()),{language:"html"}).value;$(this).html(h)});})();
