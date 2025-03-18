# AI Story Generation API

This documentation covers the AI Story Generation API endpoints for the Hekayti Dashboard application.

## Overview

The AI Story Generation API allows you to create children's stories with AI-generated text and images. The stories are generated using DeepSeek for text content and DALL-E for illustrations. The API automatically generates a story name and creates 9 slides: a cover slide plus 8 story content slides.

## Configuration

To use the API, you need to configure the following environment variables in your `.env` file:

```
DEEPSEEK_API_KEY=your_deepseek_api_key_here
OPENAI_API_KEY=your_openai_api_key_here
```

## API Endpoints

### Generate AI Story

Generates a new children's story with AI-generated text and images.

-   **URL**: `/api/generate-ai-story`
-   **Method**: `POST`
-   **Parameters**:
    -   `hero_name` (required): The name of the main character in the story
    -   `painting_style` (required): The artistic style for the illustrations (e.g., "رسوم كرتونية", "رسوم مائية")
    -   `story_topic` (required): The topic or setting of the story (e.g., "عالم الفضاء", "عالم البحار")

**Example Request:**

```json
{
    "hero_name": "سارة أحمد",
    "painting_style": "رسوم كرتونية",
    "story_topic": "عالم الفضاء"
}
```

**Example Response:**

```json
{
  "id": 1,
  "name": "رحلة سارة في الفضاء",
  "cover_photo": "/storage/ai_stories/2023-03-18_12-34-56_AbCdE/slide_0.png",
  "hero_name": "سارة أحمد",
  "painting_style": "رسوم كرتونية",
  "story_topic": "عالم الفضاء",
  "story_data": [...],
  "status": "completed",
  "created_at": "2023-03-18T12:34:56.000000Z",
  "updated_at": "2023-03-18T12:34:56.000000Z",
  "slides": [
    {
      "id": 1,
      "ai_story_id": 1,
      "page_no": 0,
      "image": "/storage/ai_stories/2023-03-18_12-34-56_AbCdE/slide_0.png",
      "text": "رحلة سارة في الفضاء",
      "created_at": "2023-03-18T12:34:56.000000Z",
      "updated_at": "2023-03-18T12:34:56.000000Z"
    },
    {
      "id": 2,
      "ai_story_id": 1,
      "page_no": 1,
      "image": "/storage/ai_stories/2023-03-18_12-34-56_AbCdE/slide_1.png",
      "text": "سارة تستعد لرحلة فضائية مثيرة.",
      "created_at": "2023-03-18T12:34:56.000000Z",
      "updated_at": "2023-03-18T12:34:56.000000Z"
    },
    ...
  ]
}
```

### Get All AI Stories

Retrieves a list of all generated AI stories with their slides.

-   **URL**: `/api/get-all-ai-stories`
-   **Method**: `GET`
-   **Parameters**: None

**Example Response:**

```json
[
  {
    "id": 1,
    "name": "رحلة سارة في الفضاء",
    "cover_photo": "/storage/ai_stories/2023-03-18_12-34-56_AbCdE/slide_0.png",
    "hero_name": "سارة أحمد",
    "painting_style": "رسوم كرتونية",
    "story_topic": "عالم الفضاء",
    "story_data": [...],
    "status": "completed",
    "created_at": "2023-03-18T12:34:56.000000Z",
    "updated_at": "2023-03-18T12:34:56.000000Z",
    "slides": [...]
  },
  ...
]
```

### Get AI Story by ID

Retrieves a specific AI-generated story by its ID with its slides.

-   **URL**: `/api/get-ai-story/{id}`
-   **Method**: `GET`
-   **Parameters**:
    -   `id` (path parameter): The ID of the story to retrieve

**Example Response:**

```json
{
  "id": 1,
  "name": "رحلة سارة في الفضاء",
  "cover_photo": "/storage/ai_stories/2023-03-18_12-34-56_AbCdE/slide_0.png",
  "hero_name": "سارة أحمد",
  "painting_style": "رسوم كرتونية",
  "story_topic": "عالم الفضاء",
  "story_data": [...],
  "status": "completed",
  "created_at": "2023-03-18T12:34:56.000000Z",
  "updated_at": "2023-03-18T12:34:56.000000Z",
  "slides": [
    {
      "id": 1,
      "ai_story_id": 1,
      "page_no": 0,
      "image": "/storage/ai_stories/2023-03-18_12-34-56_AbCdE/slide_0.png",
      "text": "رحلة سارة في الفضاء",
      "created_at": "2023-03-18T12:34:56.000000Z",
      "updated_at": "2023-03-18T12:34:56.000000Z"
    },
    ...
  ]
}
```

## Testing

You can test the AI story generation using the provided Artisan command:

```bash
php artisan app:test-ai-story-generation
```

You can also customize the parameters:

```bash
php artisan app:test-ai-story-generation --hero_name="أحمد محمد" --painting_style="رسوم زيتية" --story_topic="عالم الغابة"
```

## Story Structure

Each story consists of 9 slides:

-   **Slide 0**: Cover slide with a colorful illustration representing the story (without text on the image). The `text` field of this slide contains the story title.
-   **Slides 1-8**: Story content slides, each with text and an illustration

## Database Structure

### AiGeneratedStory Model

-   `id`: Auto-incrementing ID (used for ordering stories)
-   `name`: AI-generated name of the story (required)
-   `cover_photo`: Path to the cover photo image (required)
-   `hero_name`: Name of the main character
-   `painting_style`: Style of illustrations
-   `story_topic`: Topic of the story
-   `story_data`: Original JSON data from AI generation
-   `status`: Status of the story (e.g., 'completed')
-   `created_at`: Timestamp when the story was created
-   `updated_at`: Timestamp when the story was last updated

### AiGeneratedStorySlide Model

-   `id`: Auto-incrementing ID
-   `ai_story_id`: Foreign key to the AiGeneratedStory
-   `page_no`: Page number/order of the slide (0 for cover, 1-8 for story content)
-   `image`: Path to the image file
-   `text`: Text content of the slide
-   `created_at`: Timestamp when the slide was created
-   `updated_at`: Timestamp when the slide was last updated
