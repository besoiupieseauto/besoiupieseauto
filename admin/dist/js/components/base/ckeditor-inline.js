(()=>{$(".editor").each(function(){let e=this;e.classList.contains("ck")||CkeditorInline.create(e).then(t=>{e.parentElement.classList.add("prose","max-w-full")}).catch(t=>{console.error(t)})});})();
