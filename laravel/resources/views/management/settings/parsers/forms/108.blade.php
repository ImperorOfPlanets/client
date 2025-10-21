<!-- Поле для ввода URL -->
<div class="mb-3">
    <label for="parser-url" class="form-label">URL для парсинга</label>
    <input type="url" id="parser-url" name="url" class="form-control" placeholder="https://example.com" required>
</div>

<!-- Выпадающий список для выбора программного обеспечения -->
<div class="mb-3">
    <label for="parser-software" class="form-label">Выберите ПО для парсинга</label>
    <select id="parser-software" name="software" class="form-select" required>
        <option value="">Выберите...</option>
        <option value="selenium">Selenium</option>
        <option value="puppeteer">Puppeteer</option>
        <option value="beautifulsoup">Beautiful Soup</option>
        <option value="custom">Другое</option>
    </select>
</div>

<!-- Поле для ввода кода парсинга -->
<div class="mb-3">
    <label for="parser-code" class="form-label">Код для парсинга</label>
    <textarea id="parser-code" name="code" class="form-control" rows="5" placeholder="Введите код для обработки данных..."></textarea>
</div>

<!-- Кнопки -->
<div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-primary">Сохранить</button>
</div>