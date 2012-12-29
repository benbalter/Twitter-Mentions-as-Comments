class TMAC
    
  constructor: ->
    return unless pagenow? and pagenow is "settings_page_tmac_options"
    
    jQuery("#cron-details").siblings("input").click @toggleCron
    
    if twitter_mentions_as_comments.hide_manual_cron_details
      jQuery("#cron-details").hide()

  toggleCron: -> 
    if jQuery(this).val() is "1"
      jQuery("#cron-details").slideDown()
    else
      jQuery("#cron-details").slideUp()
    
jQuery(document).ready ->
  window.tmac = new TMAC()   
