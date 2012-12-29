class TMAC
    
  constructor: ->
    return unless pagenow? and pagenow is "settings_page_tmac_options"
    
    jQuery("#cron-details").siblings("input").click @toggleCron
    jQuery("#hide-donate").click @hideDonate
    
    if twitter_mentions_as_comments.hide_manual_cron_details
      jQuery("#cron-details").hide()

  toggleCron: -> 
    if jQuery(this).val() is "1"
      jQuery("#cron-details").slideDown()
    else
      jQuery("#cron-details").slideUp()

  hideDonate: ->
    jQuery.ajax
      url: ajaxurl + "?action=tmac_hide_donate&_ajax_nonce-tmac-hide-donate=" + jQuery("#_ajax_nonce-tmac-hide-donate").val()
      success: ->
        jQuery("#donate").fadeOut()
    event.preventDefault()
    false
    
jQuery(document).ready ->
  window.tmac = new TMAC()   
