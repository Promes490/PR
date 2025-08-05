// sendEmail.js
(function () {
    // Initialize EmailJS with your public key
    emailjs.init("MyrmrXpaRTiZFOJja");
  
    // Contact form submission
    const contactForm = document.getElementById("contactForm");
    if (contactForm) {
      contactForm.addEventListener("submit", function (e) {
        e.preventDefault();
  
        emailjs.sendForm("service_j34bmiu", "template_z3361xj", this)
          .then(function (response) {
            console.log("SUCCESS!", response.status, response.text);
            alert("Your message has been sent!");
            contactForm.reset();
          }, function (error) {
            console.log("FAILED...", error);
            alert("Something went wrong. Please try again.");
          });
      });
    }
  
    // Newsletter form submission
    const newsletterForm = document.querySelector("#mc_embed_signup form");
    if (newsletterForm) {
      newsletterForm.addEventListener("submit", function (e) {
        e.preventDefault();
  
        const emailInput = this.querySelector("input[name='EMAIL']");
        const emailValue = emailInput.value;
  
        if (!emailValue) {
          alert("Please enter your email address");
          return;
        }
  
        const templateParams = {
          subscriber_email: emailValue
        };
  
        emailjs.send("service_j34bmiu", "template_6pkqp87", templateParams)
          .then(function (response) {
            console.log("Newsletter Subscribed!", response.status, response.text);
            alert("Thank you for subscribing!");
            emailInput.value = '';
          }, function (error) {
            console.log("Newsletter FAILED...", error);
            alert("Subscription failed. Try again.");
          });
      });
    }
  })();
  