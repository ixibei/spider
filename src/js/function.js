function copyFromUrl(selClass)
{
    var clipboard = new ClipboardJS('.' + selClass);
    clipboard.on('success', function(e) {
        $('.' + selClass).css('color','green');
        var $i = $("<span/>").text('复制成功！');
        var pagex =  $("."+selClass).offset().left;
        var pagey =  $("."+selClass).offset().top;
        //console.log(pagex);
        $i.css({
            "z-index": 1000,
            "top": pagey + 5,
            "left": pagex+45,
            "position": "absolute",
            "font-weight": "bold",
            "color": "#ff6651"
        });
        $("."+selClass).append($i);
        $i.animate({
                "top": pagey - 180,
                "opacity": 0
            },
            1500,
            function() {
                $i.remove();
            });
        clipboard.destroy();
    });
    clipboard.on('error', function(e) {
        $('.' + selClass).css('color','#ccc');
        tips('复制失败！');
        clipboard.destroy();
    });
}