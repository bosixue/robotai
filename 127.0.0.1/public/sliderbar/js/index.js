$( document ).ready(function() {
 
  function createHoverState (myobject){
    myobject.hover(function() {
      $(this).prev().toggleClass('hilite');
    });
    myobject.mousedown(function() {
      $(this).prev().addClass('dragging');
      $("*").mouseup(function() {
        $(myobject).prev().removeClass('dragging');
      });
    });
  }
  
  $(".slider").slider({
    orientation: "horizontal",
    range: "min",
    max: 100,
    value: 0,
    animate: 1300
  });
  $("#blue").slider( "value", 100 );
  $('.slider').each(function(index) {
    $(this).slider( "value", 75-index*(50/($('.slider').length-1)));
  });
  
  createHoverState($(".slider a.ui-slider-handle"));

});
