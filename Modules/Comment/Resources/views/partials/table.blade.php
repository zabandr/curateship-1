<div id="table-1" class="int-table text-sm js-int-table">
  <div class="int-table__inner">
    <table class="int-table__table" aria-label="Interactive table example">
      <thead class="int-table__header js-int-table__header">
        <tr class="int-table__row">
          <td class="int-table__cell">
            <div class="custom-checkbox int-table__checkbox">
              <input class="custom-checkbox__input js-int-table__select-all" type="checkbox" aria-label="Select all rows" />
              <div class="custom-checkbox__control" aria-hidden="true"></div>
            </div>
          </td>

          <th class="int-table__cell int-table__cell--th text-left">
            Comment
          </th>

          <th class="int-table__cell int-table__cell--th int-table__cell--sort js-int-table__cell--sort" data-date-format="dd-mm-yyyy">
            <div class="flex items-center">
              <span>Date</span>

              <svg class="icon icon--xxs margin-left-xxxs int-table__sort-icon" aria-hidden="true" viewBox="0 0 12 12">
                <polygon class="arrow-up" points="6 0 10 5 2 5 6 0" />
                <polygon class="arrow-down" points="6 12 2 7 10 7 6 12" /></svg>
            </div>

            <ul class="sr-only js-int-table__sort-list">
              <li>
                <input type="radio" name="sortingDate" id="sortingDateNone" value="none" checked>
                <label for="sortingDateNone">No sorting</label>
              </li>

              <li>
                <input type="radio" name="sortingDate" id="sortingDateAsc" value="asc">
                <label for="sortingDateAsc">Sort in ascending order</label>
              </li>

              <li>
                <input type="radio" name="sortingDate" id="sortingDateDes" value="desc">
                <label for="sortingDateDes">Sort in descending order</label>
              </li>
            </ul>
          </th>

          <th class="int-table__cell int-table__cell--th text-right">Action</th>
        </tr>
      </thead>

      <tbody class="int-table__body js-int-table__body">

        <tr class="int-table__row">
          <th class="int-table__cell" scope="row">
            <div class="custom-checkbox int-table__checkbox">
              <input class="custom-checkbox__input js-int-table__select-row" type="checkbox" aria-label="Select this row" />
              <div class="custom-checkbox__control" aria-hidden="true"></div>
            </div>
          </th>
          <td class="int-table__cell text-truncate">Lorem ipsum dolor sit amet consectetur adipisicing elit. Repellat eveniet nisi itaque!
          <p class="text-component text-xs color-contrast-medium">By: username In: This is post comment is in</p>
          </td>
          <td class="int-table__cell text-component text-sm">01/01/2020</td>
          <td class="int-table__cell">
            <button class="reset int-table__menu-btn margin-left-auto js-tab-focus" data-label="Edit row" aria-controls="menu-example">
              <svg class="icon" viewBox="0 0 16 16">
                <circle cx="8" cy="7.5" r="1.5" />
                <circle cx="1.5" cy="7.5" r="1.5" />
                <circle cx="14.5" cy="7.5" r="1.5" /></svg>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<menu id="menu-example" class="menu js-menu">
  <li role="menuitem">
    <span class="menu__content js-menu__content">
      <svg class="icon menu__icon" aria-hidden="true" viewBox="0 0 12 12">
        <path d="M10.121.293a1,1,0,0,0-1.414,0L1,8,0,12l4-1,7.707-7.707a1,1,0,0,0,0-1.414Z"></path>
      </svg>
      <span>Edit</span>
    </span>
  </li>

  <li role="menuitem">
    <span class="menu__content js-menu__content">
      <svg class="icon menu__icon" aria-hidden="true" viewBox="0 0 12 12">
        <path d="M8.354,3.646a.5.5,0,0,0-.708,0L6,5.293,4.354,3.646a.5.5,0,0,0-.708.708L5.293,6,3.646,7.646a.5.5,0,0,0,.708.708L6,6.707,7.646,8.354a.5.5,0,1,0,.708-.708L6.707,6,8.354,4.354A.5.5,0,0,0,8.354,3.646Z"></path>
        <path d="M6,0a6,6,0,1,0,6,6A6.006,6.006,0,0,0,6,0ZM6,10a4,4,0,1,1,4-4A4,4,0,0,1,6,10Z"></path>
      </svg>
      <span>Delete</span>
    </span>
  </li>
</menu>