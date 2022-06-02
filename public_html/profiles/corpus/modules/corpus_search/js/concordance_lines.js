// function to sort lines by the word after the kwic
function sort_after() {
  const table = document.getElementById("table");
  const label = document.getElementById("label");
  label.innerHTML = "Lines are sorted by the word <strong>after</strong> the keyword in context.<br/>Sort by: <a href=\"#\" onclick=\"sort_before();return false;\">word before keyword</a> | <a href=\"#\" onclick=\"sort_target_asc();return false;\">target word</a> | word after keyword";
  table.innerHTML = after;
}
// function to sort lines by the word before the kwic
function sort_before() {
  const table = document.getElementById("table");
  const label = document.getElementById("label");
  label.innerHTML = "Lines are sorted by the word <strong>before</strong> the keyword in context.<br/>Sort by: word before keyword | <a href=\"#\" onclick=\"sort_target_asc();return false;\">target word</a> | <a href=\"#\" onclick=\"sort_after();return false;\">word after keyword</a>";
  table.innerHTML = before;
}
// function to sort lines by the word before the kwic
function sort_target_asc() {
  console.log('here');
  const table = document.getElementById("table");
  const label = document.getElementById("label");
  label.innerHTML = "Lines are sorted by the <strong>keyword</strong> in context.<br/>Sort by: <a href=\"#\" onclick=\"sort_before();return false;\">word before keyword</a> | target word | <a href=\"#\" onclick=\"sort_after();return false;\">word after keyword</a>";
  table.innerHTML = target_asc;
}

// Initial population.
sort_before();
