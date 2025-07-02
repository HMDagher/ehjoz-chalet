/*=== Javascript function indexing hear===========

01. headerSticky()
02. swiperActivation()
03. wowActive()
04. videoActivation()
05. jaraLux()
06. backToTopInit()
07. cookiePopup()
08. datePicker()
09. bookingTypeHandler()
10. magnificPopup()
11. mobileMenu()
12. desktopMenu()
13. stickySidebar()
14. Preloader()


==================================================*/
(function ($) {
    'use strict';
   
    var rtsJs = {
      m: function (e) {
        rtsJs.d();
        rtsJs.methods();
      },
      d: function (e) {
          this._window = $(window),
          this._document = $(document),
          this._body = $('body'),
          this._html = $('html')
      },
      methods: function (e) {
        rtsJs.headerSticky();
        rtsJs.swiperActivation();
        rtsJs.wowActive();
        rtsJs.videoActivation();
        rtsJs.jaraLux();
        rtsJs.backToTopInit();
        rtsJs.cookiePopup();
        rtsJs.datePicker();
        rtsJs.bookingTypeHandler();
        rtsJs.magnificPopup();
        rtsJs.mobileMenu();
        rtsJs.desktopMenu();
        rtsJs.stickySidebar();
        rtsJs.preloader();
      },
       // sticky Header
      headerSticky: function () {
        $(window).on("scroll", function() {
          var ScrollBarPostion = $(window).scrollTop();
          if (ScrollBarPostion > 100) {
            $(".header__function").addClass("is__sticky");      
          } else {
            $(".header__function").removeClass("is__sticky");   
          }
        });
      },
      swiperActivation: function(){
        $(document).ready(function(){
          // HERO SLIDER 
          var swiper = new Swiper(".banner__slider", {
            direction: "horizontal",
            slidesPerView: 1,
            loop: true,
            navigation: {
              nextEl: ".next",
              prevEl: ".prev",
            },
            speed: 1000,
            effect: "slide",
            autoplay: {
              delay: 5000,
              disableOnInteraction: false,
            }
          });

          var swiper = new Swiper(".apartment__slider", {
            slidesPerView: "3",
            spaceBetween: 30,
            centeredSlides: true,
            loop: true,
            speed: 1000,
            effect: "slide",
            pagination: {
              el: ".rts-pagination",
              clickable: true
            },
            breakpoints: {
              0: {
                slidesPerView: 1,
                centeredSlides: false
              },
              768: {
                slidesPerView: 2.1
              },
              1200: {
                slidesPerView: 3
              }
            }
          });

          // TESTIMONIAL SLIDER
          var swiper = new Swiper(".testimonial__slider", {
            direction: "horizontal",
            slidesPerView: 1,
            loop: true,
            speed: 1000,
            centeredSlides: true,
            autoplay: false,
            navigation: {
              nextEl: ".slider-button-next",
              prevEl: ".slider-button-prev",
            },
          });
          
          // TESTIMONIAL SLIDER
          var swiper = new Swiper(".testimonial__slider", {
            direction: "horizontal",
            slidesPerView: 1,
            loop: true,
            speed: 1000,
            centeredSlides: true,
            autoplay: false,
            navigation: {
              nextEl: ".button-next",
              prevEl: ".button-prev",
            },
          });

          // APARTMENT SLIDER
          var apartMent = new Swiper(".room__slider", {
            slidesPerView: 3,
            spaceBetween: 30,
            loop: true,
            speed: 1000,
            centeredSlides: true,
            autoplay: false,
            pagination: {
              el: ".rts-pagination",
              clickable: true
            },
            breakpoints: {
              0: {
                slidesPerView: 1,
              },
              575: {
                slidesPerView: 1,
              },
              768: {
                slidesPerView: 2,
                centeredSlides: false
              },
              1200: {
                slidesPerView: 3,
              },
              1400: {
                slidesPerView: 3,
              }
            }
          });

          // APARTMENT SLIDER
          var apartMent = new Swiper(".main__room__slider", {
            slidesPerView: 4,
            spaceBetween: 30,
            loop: true,
            speed: 1000,
            centeredSlides: false,
            autoplay: false,
            pagination: {
              el: ".rts-pagination",
              clickable: true
            },
            breakpoints: {
              0: {
                slidesPerView: 1,
              },
              575: {
                slidesPerView: 1,
              },
              768: {
                slidesPerView: 2,
                centeredSlides: false
              },
              992: {
                slidesPerView: 2.5,
                centeredSlides: false
              },
              1200: {
                slidesPerView: 3,
              },
              1400: {
                slidesPerView: 4,
              }
            }
          });

        // GALLERY SLIDER
        let Gallery = new Swiper(".gallery__slider", {
          slidesPerView: 4,
          spaceBetween: 30,
          loop: true,
          speed: 1000,
          autoplay: true,
          navigation: {
            nextEl: ".slider-button-next",
            prevEl: ".slider-button-prev",
          },
          breakpoints: {
            0: {
              slidesPerView: 1,
            },
            575: {
              slidesPerView: 2,
            },
            768: {
              slidesPerView: 3,
            },
            1200: {
              slidesPerView: 3,
            },
            1400: {
              slidesPerView: 4,
            }
          }
        });

        // ROOM SLIDER FOUR
        let RoomFour = new Swiper(".room__slider.is__home__four", {
          slidesPerView: 2,
          spaceBetween: 30,
          loop: true,
          speed: 1000,
          autoplay: true,
          navigation: {
            nextEl: ".slider-button-next",
            prevEl: ".slider-button-prev",
          },
          breakpoints: {
            0: {
              slidesPerView: 1,
            },
            768: {
              slidesPerView: 2,
            },  
            1200: {
              slidesPerView: 2,
            },
          }
        });

        // SERVICE SLIDER HOME FOUR
        let serviceFour = new Swiper(".service__slider", {
          slidesPerView: 4,
          spaceBetween: 30,
          loop: true,
          speed: 1000,
          autoplay: true,
          pagination: {
            el: ".rts-pagination",
            clickable: true
          },
          breakpoints: {
            0: {
              slidesPerView: 1,
            },
            767: {
              slidesPerView: 2.01,
            },
            991: {
              slidesPerView: 2.5,
            },
            1200: {
              slidesPerView: 3,
            },
            1400: {
              slidesPerView: 3,
            },
            1600: {
              slidesPerView: 4,
            }
          }
        });

        // SERVICE SLIDER HOME FIVE
        let serviceFive = new Swiper(".service__slider__five", {
          slidesPerView: 3.05,
          spaceBetween: 30,
          loop: true,
          speed: 1000,
          autoplay: true,
          centeredSlides: true,
          navigation: {
            nextEl: ".slider-button-next",
            prevEl: ".slider-button-prev",
          },
          breakpoints: {
            0: {
              slidesPerView: 1,
              centeredSlides: false,
            },
            767: {
              slidesPerView: 2.01,
              centeredSlides: false,
            },
            991: {
              slidesPerView: 2.5,
              centeredSlides: false,
            },
            1200: {
              slidesPerView: 3,
            },
            1400: {
              slidesPerView: 3,
            }
          }
        });

        // VIDEO TEXT SLIDER
        let videoText = new Swiper(".video__text__slider", {
          slidesPerView: 1,
          spaceBetween: 0,
          loop: true,
          speed: 1000,
          autoplay: true,
          autoplay: {
            delay: 5000,
          }
        });

        // Initialize the main slider (model__hero__top)
        var mainSlider = new Swiper('.testimonial__author', {
            slidesPerView: 1,
            loop: true,
            speed: 1000,
            grabCursor: true,
            effect: 'fade',
        });

        // Initialize the thumbnail slider (model__hero__slider)
        var thumbSlider = new Swiper('.tm__slider__five', {
            slidesPerView: 1,
            loop: true,
            speed: 1000,
            effect: 'slide',
            grabCursor: true,
            autoplay: {
                delay: 3000,
            },
            navigation: {
                nextEl: ".button-next",
                prevEl: ".button-prev",
            },
        });
        // Synchronize the two sliders
        mainSlider.controller.control = thumbSlider;
        thumbSlider.controller.control = mainSlider;
        });
         
        // instagram slider
        var instaSlider = new Swiper('.insta__gallery__slider', {
            slidesPerView: 6,
            loop: true,
            speed: 1000,
            spaceBetween: 15,
            grabCursor: true,
            autoplay: {
                delay: 3000,
            },
            breakpoints: {
              0: {
                slidesPerView: 1,
              },
              480: {
                slidesPerView: 2,
              },
              576: {
                slidesPerView: 2,
              },
              768: {
                slidesPerView: 3,
              },
              992: {
                slidesPerView: 4,
              },
              1200: {
                slidesPerView: 6,
              },
            }
          });

      },  
      wowActive: function () {
        new WOW().init();
      },

      videoActivation: function (e) {
        $(document).ready(function() {
          $('.popup-youtube, .popup-vimeo, .popup-gmaps, .video-play').magnificPopup({
            disableOn: 700,
            type: 'iframe',
            mainClass: 'mfp-fade',
            removalDelay: 160,
            preloader: false,
            fixedContentPos: false
          });
        });
      },

      jaraLux: function (e) {
        $(document).ready(function() {
          // Function to detect if the device is mobile
          function isMobileDevice() {
              return /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
          }
      
          // Initialize jarallax only if it's not a mobile device
          if (!isMobileDevice()) {
              $('.jarallax').jarallax();
          } else {
              console.log('Jarallax skipped on mobile devices');
          }
      });
      
      },

      backToTopInit: function (e) {
        $(document).ready(function () {
          var backButton = $("#rts-back-to-top");
          $(window).scroll(function () {
            if ($(this).scrollTop() > 100) {
              backButton.addClass("show");
            } else {
              backButton.removeClass("show");
            }
          });
          backButton.on("click", function () {
            $("html, body").animate(
              {
                scrollTop: 0,
              },
              1000
            );
          });
        });
      },

      cookiePopup: function (e) {
        $.gdprcookie.init();
      },
      datePicker: function (e) {
        $(function () {
          // Check-in: only today or future
          $("#check__in").datepicker({
              dateFormat: "dd-mm-yy",
              duration: "fast",
              minDate: 0
          });
          // Checkout: minDate will be set dynamically
          $("#check__out").datepicker({
              dateFormat: "dd-mm-yy",
              duration: "fast"
          });

          // When check-in changes, update checkout's minDate
          $('#check__in').on('change', function() {
            var checkInDate = $(this).datepicker('getDate');
            if (checkInDate) {
              // Add one day to check-in for minDate
              var minCheckout = new Date(checkInDate.getTime());
              minCheckout.setDate(minCheckout.getDate() + 1);
              $('#check__out').datepicker('option', 'minDate', minCheckout);
              // If checkout is before or same as check-in, clear it
              var checkOutDate = $('#check__out').datepicker('getDate');
              if (!checkOutDate || checkOutDate <= checkInDate) {
                $('#check__out').val('');
              }
            } else {
              // If no check-in, allow any checkout
              $('#check__out').datepicker('option', 'minDate', null);
            }
          });
        });
      },
      
      bookingTypeHandler: function (e) {
        $(function() {
          // Function to toggle checkout field visibility based on booking type
          function toggleCheckoutField() {
            var bookingType = $("#booking_type").val();
            if (bookingType === "day-use") {
              $(".checkout-field").hide();
              $("#check__out").prop("required", false);
            } else {
              $(".checkout-field").show();
              $("#check__out").prop("required", true);
            }
          }
          
          // Initial call to set correct state on page load
          toggleCheckoutField();
          
          // Event listener for booking type change
          $("#booking_type").on("change", function() {
            toggleCheckoutField();
          });
        });
      },
      
      magnificPopup: function (e) {
        $('.gallery').each(function() { 
          $(this).magnificPopup({
              delegate: 'a', 
              type: 'image',
              gallery: {
                enabled:true
              }
          });
      });
      },
      mobileMenu: function (e) {
        try {
          $(".offcanvas__menu").meanmenu({
            meanMenuContainer: ".mobile__menu__active",
            meanScreenWidth: "991",
            meanExpand: ["+"],
          });
        } catch (error) {
          console.log(error);
        }
      },
      desktopMenu: function (e) {
        $(document).ready(function() {
          $('.has__children .toggle').on('click', function(event) {
              event.preventDefault(); // Prevent the default action of the click event
              
              var $parentLi = $(this).closest('li'); // Get the closest <li> element
      
              // Toggle the 'active' class on the parent <li> to show/hide the submenu
              $parentLi.toggleClass('active');
              
              // Optionally, close other open menus by removing the 'active' class from other <li> elements
              $('.has__children').not($parentLi).removeClass('active');
          });
      });
      },
      stickySidebar: function (e) {
        if (typeof $.fn.theiaStickySidebar !== "undefined") {
          $(".sticky-wrap .sticky-item").theiaStickySidebar({
            additionalMarginTop: 100,
          });
        }
      },
      preloader: function (e){
        window.addEventListener('load',function(){
          document.querySelector('body').classList.add("loaded")  
        });     
      }

    }
    rtsJs.m(); 

  
  })(jQuery, window)  

// Add this function to fetch valid slot combinations
function fetchValidSlotCombinations(chaletId, date) {
    return $.ajax({
        url: '/bookings/consecutive-slot-combinations',
        method: 'GET',
        data: { chalet_id: chaletId, date: date }
    });
}

// Update checkAvailability for day-use to use valid combinations
function checkAvailability() {
    const bookingType = $("#booking_type").val();
    const startDate = $("#check__in").val();
    const endDate = $("#check__out").val();

    if (!startDate) {
        return;
    }

    const formattedStartDate = convertDateFormat(startDate);
    const formattedEndDate = endDate ? convertDateFormat(endDate) : null;

    $("#book-button").prop("disabled", true).text("Checking...");

    if (bookingType === 'day-use') {
        // Use the same AJAX pattern as overnight
        $.ajax({
            url: `/api/chalet/${chaletSlug}/availability`,
            method: 'GET',
            data: {
                booking_type: bookingType,
                start_date: formattedStartDate,
                end_date: formattedEndDate
            },
            success: function(response) {
                if (response.success && response.data.slots.length > 0) {
                    displayDayUseSlots(response.data);
                    $("#book-button").prop("disabled", false).text("Book Now");
                } else {
                    $("#available-slots-container").hide();
                    showError("No available slots for selected date");
                    $("#book-button").prop("disabled", true).text("Check Availability");
                }
            },
            error: function(xhr) {
                $("#available-slots-container").hide();
                const error = xhr.responseJSON?.error || "Error checking availability";
                showError(error);
                $("#book-button").prop("disabled", true).text("Check Availability");
            }
        });
    } else {
        // ... existing overnight logic ...
    }
}

function displayDayUseSlots(data) {
    const container = $("#available-slot-combinations-list");
    container.empty();
    if (!data.slots || data.slots.length === 0) {
        container.html('<p class="text-danger">No available slots for selected date</p>');
        $("#available-slots-container").hide();
        return;
    }
    data.slots.forEach(slot => {
        let priceDisplay = `$${slot.price}`;
        let additionalAttributes = '';
        if (slot.has_discount) {
            priceDisplay = `<span class="text-decoration-line-through">$${slot.original_price}</span> <span class="text-success">$${slot.price}</span>`;
            additionalAttributes = `data-original-price="${slot.original_price}" data-has-discount="1" data-discount-percentage="${slot.discount_percentage}"`;
        }
        const slotHtml = `
            <div class="form-check mb-2">
                <input class="form-check-input slot-checkbox" type="checkbox" 
                       value="${slot.id}" id="slot_${slot.id}" 
                       data-price="${slot.price}" data-name="${slot.name}" ${additionalAttributes}>
                <label class="form-check-label" for="slot_${slot.id}">
                    <strong>${slot.name}</strong> (${slot.start_time} - ${slot.end_time}, ${slot.duration_hours} hrs) - ${priceDisplay}
                    ${slot.has_discount ? '<span class="badge bg-success ms-1">15% OFF</span>' : ''}
                </label>
            </div>
        `;
        container.append(slotHtml);
    });
    $("#available-slots-container").show();
}

// Update submitBooking for day-use to use selected combo
function submitBooking() {
    const bookingType = $("#booking_type").val();
    const startDate = convertDateFormat($("#check__in").val());
    let endDate = null;
    let slotIds = [];
    if (bookingType === 'day-use') {
        const selected = $(".slot-combo-radio:checked");
        if (selected.length === 0) {
            showError("Please select a valid slot combination");
            return;
        }
        slotIds = selected.val().split(",");
        endDate = startDate;
    } else {
        // ... existing overnight logic ...
    }
    // ... existing bookingData logic ...
}

// Update error handling to show API error messages
function submitBookingWithData(bookingData) {
    $.ajax({
        url: '/api/bookings',
        method: 'POST',
        data: bookingData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                showSuccess("Booking created successfully! Redirecting to confirmation page...");
                setTimeout(() => {
                    window.location.href = response.data.confirmation_url;
                }, 2000);
            } else {
                showError(response.error || "Error creating booking");
            }
        },
        error: function(xhr) {
            const error = xhr.responseJSON?.error || "Error creating booking";
            showError(error);
            $("#book-button").prop("disabled", false).text("Book Now");
        }
    });
}

// --- Search Form Slot Combination Logic ---
$(function() {
    // Only run if the search form is present
    if ($('form.advance__search').length > 0) {
        let searchChaletId = null; // You may need to set this dynamically if searching by chalet
        // Listen for booking type or date change
        $('#booking_type, #check__in').on('change', function() {
            const bookingType = $('#booking_type').val();
            const startDate = $('#check__in').val();
            // You may need to get the selected chalet ID if your search supports it
            // For now, assume a global or default chaletId, or skip if not available
            if (!searchChaletId) return;
            if (bookingType === 'day-use' && startDate) {
                const formattedStartDate = convertDateFormat(startDate);
                fetchValidSlotCombinations(searchChaletId, formattedStartDate)
                    .done(function(response) {
                        if (response.success && response.data.length > 0) {
                            displaySearchDayUseSlotCombinations(response.data);
                            $('#search-available-slots-container').show();
                        } else {
                            $('#search-available-slots-container').hide();
                            showError("No available slot combinations for selected date");
                        }
                    })
                    .fail(function() {
                        $('#search-available-slots-container').hide();
                        showError("Error fetching slot combinations");
                    });
            } else {
                $('#search-available-slots-container').hide();
            }
        });
    }
});

function displaySearchDayUseSlotCombinations(combinations) {
    const container = $('#search-available-slot-combinations-list');
    container.empty();
    if (combinations.length === 0) {
        container.html('<p class="text-danger">No available slot combinations for selected date</p>');
        return;
    }
    combinations.forEach((combo, idx) => {
        const slotNames = combo.slots.map(s => s.name).join(", ");
        const slotIds = combo.slots.map(s => s.id).join(",");
        const price = combo.total_price.toFixed(2);
        const checked = idx === 0 ? 'checked' : '';
        const html = `
            <div class="form-check mb-2">
                <input class="form-check-input search-slot-combo-radio" type="radio" name="search_slot_combo" value="${slotIds}" id="search_combo_${idx}" data-price="${price}" data-names="${slotNames}" ${checked}>
                <label class="form-check-label" for="search_combo_${idx}">
                    <strong>${slotNames}</strong> (${combo.start_time} - ${combo.end_time}, ${combo.total_duration} hrs) - $${price}
                </label>
            </div>
        `;
        container.append(html);
    });
    // Trigger summary update for first combo
    updateSearchSelectedCombo();
}

$(document).on('change', '.search-slot-combo-radio', function() {
    updateSearchSelectedCombo();
});

function updateSearchSelectedCombo() {
    const selected = $('.search-slot-combo-radio:checked');
    if (selected.length === 0) {
        $('#search-selected-combo-summary').hide();
        return;
    }
    const names = selected.data('names');
    const price = parseFloat(selected.data('price'));
    $('#search-selected-combo-text').text(names);
    $('#search-combo-total-price').text(price.toFixed(2));
    $('#search-selected-combo-summary').show();
}

