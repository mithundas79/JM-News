jQuery(document).ready(function($) {

	var metaBoxRootDiv = $("#oz_news_more_info_box").parent();

	var metaBoxes = $(metaBoxRootDiv).find('[id^=oz_news_][id$=box]');
	if(typeof metaBoxes !='undefined'){
		var tabHeader = '<ul>';
		$.each(metaBoxes, function(index, item){
			//console.log(item);
			tabHeader += '<li><a href="#'+$(item).attr('id')+'">'+$(item).find('h3.hndle').find('span').html()+'</a> </li>';
		});
		tabHeader += '</ul>';
		$(metaBoxRootDiv).prepend($(tabHeader));
		$(metaBoxRootDiv).addClass('tabs');
		$(metaBoxRootDiv).tabs();
	}

	if ($('#_cmb_news_leading_asset_type').length){

		$('.cmb_id__cmb_news_leading_asset_image').addClass('hide_cmb_field');
		$('.cmb_id__cmb_news_leading_asset_video').addClass('hide_cmb_field');

		if (jQuery('#_cmb_news_leading_asset_type').val() == 'image'){
			jQuery('.cmb_id__cmb_news_leading_asset_image').show()
		}
		else {
			jQuery('.cmb_id__cmb_news_leading_asset_image').hide();
		}
		jQuery('#_cmb_news_leading_asset_type').change(function() {
			var selected = jQuery(this).val();
			if(selected == 'image'){
				jQuery('.cmb_id__cmb_news_leading_asset_image').show();
			} else {
				jQuery('.cmb_id__cmb_news_leading_asset_image').hide();
			}
		});


		if (jQuery('#_cmb_news_leading_asset_type').val() == 'video'){
			jQuery('.cmb_id__cmb_news_leading_asset_video').show()
		}
		else {
			jQuery('.cmb_id__cmb_news_leading_asset_video').hide();
		}
		jQuery('#_cmb_news_leading_asset_type').change(function() {
			var selected = jQuery(this).val();
			if(selected == 'video'){
				jQuery('.cmb_id__cmb_news_leading_asset_video').show();
			} else {
				jQuery('.cmb_id__cmb_news_leading_asset_video').hide();
			}
		});

	}


	//spotlight image
	if ($('#_cmb_news_spotlight').length){

		$('.cmb_id__cmb_news_spotlight_image').addClass('hide_cmb_field');

		if ($('#_cmb_news_spotlight').val() == "1"){
			jQuery('.cmb_id__cmb_news_spotlight_image').show()
		}
		else {
			jQuery('.cmb_id__cmb_news_spotlight_image').hide();
		}
		$('#_cmb_news_spotlight').change(function() {
			var selected = jQuery(this).val();
			if(selected == '1'){
				jQuery('.cmb_id__cmb_news_spotlight_image').show();
			} else {
				jQuery('.cmb_id__cmb_news_spotlight_image').hide();
			}
		});

	}
});