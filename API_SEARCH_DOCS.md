# API Search Documentation

## Search Endpoint

**URL**: `/?action=search`  
**Method**: `GET`

### Parameters

| Parameter | Type | Required | Description | Default |
|-----------|------|----------|-------------|---------|
| `q` or `query` | string | No | Search query string | `''` |
| `page` | integer | No | Page number (1-based) | `1` |
| `per_page` | integer | No | Results per page (1-100) | `20` |
| `status` | string | No | Filter by status (`active`, `inactive`) | `null` |
| `plan` | string | No | Filter by plan name | `null` |

### Search Fields

The search functionality searches across:
- **Domain name** (`zone.name`)
- **Zone status** (`zone.status`) 
- **Plan name** (`zone.plan.name`)
- **Zone ID** (`zone.id`)

### Response Format

```json
{
  "success": true,
  "data": {
    "result": [
      {
        "id": "zone_id",
        "name": "example.com",
        "status": "active",
        "plan": {
          "name": "Free"
        },
        "created_on": "2026-01-01T00:00:00Z",
        "ssl": true
      }
    ],
    "result_info": {
      "count": 1,
      "page": 1,
      "per_page": 20,
      "total_count": 1
    }
  },
  "search": {
    "query": "example",
    "page": 1,
    "per_page": 20,
    "filters": {
      "status": null,
      "plan": null
    },
    "total_results": 1,
    "search_fields": ["name", "status", "plan.name", "id"]
  }
}
```

### Example Requests

**Basic search:**
```
GET /?action=search&q=example
```

**Search with filters:**
```
GET /?action=search&q=com&status=active&plan=Free
```

**Paginated search:**
```
GET /?action=search&q=domain&page=1&per_page=10
```

### Error Response

```json
{
  "success": false,
  "error": "Error message",
  "search": {
    "query": "example",
    "message": "Search failed"
  }
}
```

## Frontend Integration

The frontend now supports both **API search** and **client-side search**:

- **API Search**: Real-time search via server API (default)
- **Local Search**: Client-side filtering of loaded data
- **Hybrid Mode**: Toggle between API and local search
- **Debouncing**: 300ms delay for API calls to prevent spam
- **Fallback**: Automatic fallback to local search if API fails

### Features

✅ **Real-time search** with debouncing  
✅ **Highlight matching text** in results  
✅ **Search result counters**  
✅ **API/Local mode toggle**  
✅ **Error handling with fallback**  
✅ **Keyboard shortcuts** (Ctrl+F, Ctrl+K, /, ESC)  
✅ **Responsive design**