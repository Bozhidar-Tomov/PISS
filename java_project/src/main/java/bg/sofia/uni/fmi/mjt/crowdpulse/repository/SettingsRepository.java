package bg.sofia.uni.fmi.mjt.crowdpulse.repository;

import bg.sofia.uni.fmi.mjt.crowdpulse.config.DatabaseConfig;
import com.mongodb.client.MongoCollection;
import com.mongodb.client.model.Filters;
import com.mongodb.client.model.Updates;
import org.bson.Document;
import org.bson.conversions.Bson;

public class SettingsRepository {
    private final MongoCollection<Document> collection;

    public SettingsRepository() {
        this.collection = DatabaseConfig.getDatabase().getCollection("settings");
    }

    public String getValue(String key) {
        Document doc = collection.find(Filters.eq("_id", key)).first(); // Using _id as key
        return doc != null ? doc.getString("value") : null;
    }

    public void setValue(String key, String value) {
        Bson filter = Filters.eq("_id", key);
        Bson update = Updates.set("value", value);

        // Upsert
        if (collection.countDocuments(filter) > 0) {
            collection.updateOne(filter, update);
        } else {
            Document doc = new Document("_id", key).append("value", value);
            collection.insertOne(doc);
        }
    }
}
