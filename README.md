# Modern Single-File PHP CMS

![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![SQLite](https://img.shields.io/badge/SQLite-3-green)
![License](https://img.shields.io/badge/License-MIT-yellow)
![Size](https://img.shields.io/badge/Size-Single%20File-orange)

A beautiful, powerful, and feature-rich Content Management System contained in a single PHP file. Perfect for quick deployments, small to medium websites, and developers who want a lightweight yet comprehensive CMS solution.

## ✨ Features

### 🎯 Core Features
- **Single File Deployment** - Everything in one `cms.php` file
- **SQLite Database** - No complex database setup required
- **Beautiful Admin Interface** - Modern, responsive design with Tailwind CSS
- **WYSIWYG Editor** - Rich text editing with toolbar formatting
- **Media Management** - Upload, organize, and link files with drag-and-drop
- **Dynamic Table Creation** - Create custom content types on the fly
- **REST API** - Full JSON API for external integrations

### 🌍 Multi-Language Support
- **Translation Management** - Translate content into multiple languages
- **Language Switching** - Easy language management interface
- **API Translation Support** - Get translated content via API
- **Default Language Fallback** - Graceful handling of missing translations
- **10+ Pre-configured Languages** - English, Spanish, French, German, Italian, Portuguese, Arabic, Chinese, Japanese, Russian

### 🔧 Advanced Features
- **Foreign Key Relationships** - Link content between tables
- **Media Field Types** - Single and multiple file attachments
- **Search Functionality** - Full-text search across all content
- **Field Type System** - Text, Long Text, Numbers, Dates, Booleans, Media, Foreign Keys
- **Responsive Design** - Works perfectly on desktop, tablet, and mobile
- **Setup Wizard** - Guided installation process

### 📊 Developer Features
- **RESTful API** - Complete CRUD operations
- **JSON Responses** - Structured data format
- **CORS Support** - Cross-origin requests enabled
- **Media API** - File management endpoints
- **Translation API** - Multi-language content access
- **Search API** - Query content programmatically

## 🚀 Quick Start

### Installation

1. **Download** the `cms.php` file
2. **Upload** to your web server
3. **Visit** the URL in your browser
4. **Follow** the setup wizard

```bash
# Using wget
wget https://raw.githubusercontent.com/your-repo/cms.php

# Using curl
curl -O https://raw.githubusercontent.com/your-repo/cms.php
```

### Requirements

- **PHP 7.4+** (PHP 8.0+ recommended)
- **SQLite PDO Extension** (usually included)
- **Web Server** (Apache, Nginx, or built-in PHP server)

### First Steps

1. **Run Setup**: Navigate to your CMS URL and complete the setup wizard
2. **Create Content**: Use the admin interface to create your first table and content
3. **Configure Languages**: Set up multi-language support if needed
4. **API Access**: Start using the REST API for external integrations

## 🔧 Usage Examples

### Basic Content Management

```php
// Create a blog
1. Add a new table called "articles"
2. Add fields: title (text), content (long text), author (text), date (date)
3. Start writing articles!
```

### Multi-Language Content

```php
// Translate your content
1. Go to Languages management
2. Activate desired languages (French, Spanish, etc.)
3. Edit any content record
4. Use the language tabs to add translations
5. Access via API with ?lang=fr parameter
```

### Media Management

```php
// Upload and link media
1. Go to Media section
2. Upload images, documents, videos
3. Create content with Media field types
4. Link media to your content records
```

## 🌐 API Usage

### Get Content

```javascript
// Get all articles
fetch('/cms.php?api=records&table=articles')
  .then(response => response.json())
  .then(data => console.log(data.data));

// Get articles in French
fetch('/cms.php?api=records&table=articles&lang=fr')
  .then(response => response.json())
  .then(data => {
    data.data.forEach(article => {
      console.log('Original:', article.title);
      console.log('French:', article.translations.title);
    });
  });
```

### Get Languages

```javascript
// Get available languages
fetch('/cms.php?api=languages')
  .then(response => response.json())
  .then(data => console.log(data.data.languages));
```

### Get Translations

```javascript
// Get all translations for a specific article
fetch('/cms.php?api=translations&table=articles&record_id=1')
  .then(response => response.json())
  .then(data => console.log(data.data.translations));
```

### PHP Examples

```php
// Get content for your website
$articles = json_decode(
  file_get_contents('https://yoursite.com/cms.php?api=records&table=articles&lang=es'), 
  true
);

foreach ($articles['data'] as $article) {
  $title = $article['translations']['title'] ?? $article['title'];
  echo "<h2>{$title}</h2>";
}
```

## 📋 Available API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/cms.php?api=tables` | GET | List all tables and their structure |
| `/cms.php?api=records&table=X` | GET | Get records from table X |
| `/cms.php?api=record&table=X&id=Y` | GET | Get specific record by ID |
| `/cms.php?api=media` | GET | List media files |
| `/cms.php?api=search&table=X&q=Y` | GET | Search records in table X |
| `/cms.php?api=languages` | GET | Get available languages |
| `/cms.php?api=translations&table=X&record_id=Y` | GET | Get translations for record |

### API Parameters

- `lang` - Language code (fr, es, de, etc.) for translated content
- `limit` - Number of records to return (default: 10, max: 100)
- `offset` - Number of records to skip for pagination
- `order_by` - Field to sort by (default: id)
- `order_dir` - Sort direction: ASC or DESC (default: DESC)

## 🎨 Customization

### Styling
The CMS uses Tailwind CSS loaded from CDN. You can customize the appearance by:
- Modifying the CSS classes in the code
- Adding custom CSS styles
- Replacing Tailwind with your own CSS framework

### Functionality
Being a single file, you can easily:
- Add new field types
- Modify the API responses
- Customize the admin interface
- Add authentication methods
- Extend the translation system

## 🔒 Security Features

- **Password Hashing** - Secure password storage with PHP's password_hash()
- **SQL Injection Protection** - All queries use prepared statements
- **XSS Prevention** - Output escaping with htmlspecialchars()
- **File Upload Validation** - MIME type and size restrictions
- **Session Management** - Secure admin session handling

## 🌟 Use Cases

### Perfect For:
- **Small Business Websites** - Quick content management
- **Portfolio Sites** - Showcase work with media galleries
- **Blogs** - Multi-language blogging platform
- **API Backend** - Headless CMS for mobile apps
- **Prototyping** - Rapid content modeling and testing
- **Learning** - Understanding CMS architecture

### Examples:
- Restaurant menus in multiple languages
- Product catalogs with images
- News sites with categories
- Event listings with details
- Team member profiles
- Project portfolios

## 📊 Technical Details

### Database Schema
- **Dynamic Tables** - User-defined content structures
- **System Tables** - users, media, languages, translations, metadata
- **Foreign Keys** - Relationships between content
- **Media Linking** - File attachment system

### Architecture
- **MVC Pattern** - Clean separation of concerns
- **RESTful API** - Standard HTTP methods and responses
- **Responsive Design** - Mobile-first approach
- **Progressive Enhancement** - Works without JavaScript

### File Structure
```
cms.php (Everything is here!)
├── PHP Classes & Logic
├── HTML Templates
├── CSS Styles (Tailwind CDN)
├── JavaScript Functions
├── API Endpoints
└── Database Schema
```

## 🛠️ Development

### Local Development

```bash
# Using PHP built-in server
php -S localhost:8000 cms.php

# Visit http://localhost:8000
```

### Docker Development

```dockerfile
FROM php:8.1-apache
RUN docker-php-ext-install pdo pdo_sqlite
COPY cms.php /var/www/html/index.php
```

## 🤝 Contributing

This is a single-file project, making contributions straightforward:

1. **Fork** the repository
2. **Modify** the `cms.php` file
3. **Test** your changes thoroughly
4. **Submit** a pull request

### Areas for Contribution:
- New field types
- Additional languages
- Security enhancements
- Performance optimizations
- UI/UX improvements
- API extensions

## 📝 Changelog

### Version 2.0 (Current)
- ✅ Multi-language translation system
- ✅ Enhanced API with translation support
- ✅ Improved WYSIWYG editor
- ✅ Language management interface
- ✅ Translation API endpoints
- ✅ Interactive API documentation

### Version 1.0
- ✅ Single-file CMS architecture
- ✅ SQLite database integration
- ✅ Admin interface
- ✅ Content management
- ✅ Media upload system
- ✅ Basic REST API

## 🐛 Known Issues

- **Large Files** - PHP upload limits may restrict large media files
- **Concurrent Editing** - No real-time collaboration features
- **Advanced Permissions** - Single admin role only
- **Database Backup** - Manual SQLite file backup required

## 📄 License

MIT License - feel free to use this CMS for personal and commercial projects.

## 🆘 Support

### Documentation
- **Built-in Help** - Admin interface includes comprehensive guides
- **API Docs** - Interactive documentation within the CMS
- **Code Comments** - Well-documented source code

### Community
- **GitHub Issues** - Bug reports and feature requests
- **Discussions** - Community support and ideas
- **Wiki** - Extended documentation and tutorials

## 🎯 Roadmap

### Planned Features
- [ ] **User Roles** - Multiple user types with permissions
- [ ] **Plugin System** - Extensible architecture
- [ ] **Themes** - Template system for different designs
- [ ] **Backup/Export** - Data export and import tools
- [ ] **Advanced Search** - Full-text search with filters
- [ ] **Caching** - Performance optimization features

### Community Requests
- [ ] **Multi-site Management** - Manage multiple sites
- [ ] **Workflow System** - Content approval process
- [ ] **SEO Tools** - Built-in SEO optimization
- [ ] **Analytics** - Basic usage statistics
- [ ] **Email Integration** - Contact forms and notifications

## 🌐 Demo

**Live Demo**: [View Demo Site](https://demo.yoursite.com)
- Username: `demo`
- Password: `demo123`

**API Demo**: [API Explorer](https://demo.yoursite.com/cms.php?page=api-docs)

---

## 🚀 Get Started Now!

Download the single `cms.php` file and start building your content-managed website in minutes!

```bash
# Quick start
curl -O https://raw.githubusercontent.com/your-repo/cms.php
php -S localhost:8000 cms.php
# Visit http://localhost:8000 and enjoy!
```

---

**Made with ❤️ for developers who love simplicity without sacrificing power.**
