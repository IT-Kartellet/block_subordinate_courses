(function(){
	$("div.expandable > h3").click(function() {
		$(this).siblings().stop().slideToggle(200);
	});
})();