$(document).ready(function(){
  
  // REFINE SEARCH FILTERS

  // first - Search
  $('h6.togglable:eq( 0 )').click(function(){
    $('.sidebar-filter-entries:eq( 0 )').slideToggle(200);
  });

  // second - filter by Year
  $('h6.togglable:eq( 1 )').click(function(){
    $('.sidebar-filter-entries:eq( 1 )').slideToggle(200);
  });

  // third - filter by Access
  $('h6.togglable:eq( 2 )').click(function(){
    $('.sidebar-filter-entries:eq( 2 )').slideToggle(200);
  });

  // fourth - filter by Region
  $('h6.togglable:eq( 3 )').click(function(){
    $('.sidebar-filter-entries:eq( 3 )').slideToggle(200);
  });

  // fifth - filter by Income
  $('h6.togglable:eq( 4 )').click(function(){
    $('.sidebar-filter-entries:eq( 4 )').slideToggle(200);
  });

  // fifth - filter by Collection
  $('h6.togglable:eq( 5 )').click(function(){
    $('.sidebar-filter-entries:eq( 5 )').slideToggle(200);
  });

  // fifth - filter by Country
  $('h6.togglable:eq( 6 )').click(function(){
    $('.sidebar-filter-entries:eq( 6 )').slideToggle(200);
  });


  // END OF REFINE SEARCH FILTERS


  // KNOWLEDGE CENTER
  $('.wb-question:eq( 0 )').click(function(){
    $('.wb-answer:eq( 0 )').slideToggle(200);
  });
  
  $('.wb-question:eq( 1 )').click(function(){
    $('.wb-answer:eq( 1 )').slideToggle(200);
  });

  $('.wb-question:eq( 2 )').click(function(){
    $('.wb-answer:eq( 2 )').slideToggle(200);
  });

  $('.wb-question:eq( 3 )').click(function(){
    $('.wb-answer:eq( 3 )').slideToggle(200);
  });

  $('.wb-question:eq( 4 )').click(function(){
    $('.wb-answer:eq( 4 )').slideToggle(200);
  });
  
});


