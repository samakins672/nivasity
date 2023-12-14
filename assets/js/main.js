function showAlert() {
  // Automatically show and hide the alert after 5 seconds
  $('#alertBanner').fadeIn();

  setTimeout(function () {
    $('#alertBanner').fadeOut();
  }, 5000);
}

function getAdmissionYears() {
  let $selectElement = $("#admissionYear");

  // Get the current year
  let currentYear = new Date().getFullYear();

  // Set the starting year
  let startYear = 2019;

  // Loop through the years and generate the options
  for (let year = currentYear + 1; year >= startYear; year--) {
    let option = $("<option/>", {
      value: `${year - 1}/${year}`,
      text: `${year - 1}/${year}`
    });

    $selectElement.append(option);
  }
}