// function to sort lines by the word after the kwic
function sort_after() {
  const table = document.getElementById("table");
  const label = document.getElementById("label");
  label.innerHTML = "The lines below are sorted by the word <strong>after</strong> the keyword in context. <a href=\"#\" onclick=\"sort_before();return false;\">Sort by the word before</a>.";
  table.innerHTML = after;
}
// function to sort lines by the word before the kwic
function sort_before() {
  const table = document.getElementById("table");
  const label = document.getElementById("label");
  label.innerHTML = "The lines below are sorted by the word <strong>before</strong> the keyword in context. <a href=\"#\" onclick=\"sort_after();return false;\">Sort by the word after</a>.";
  table.innerHTML = before;
}

// Initial population.
sort_before();
