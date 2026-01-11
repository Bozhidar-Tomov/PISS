package bg.sofia.uni.fmi.mjt.crowdpulse.repository;

import bg.sofia.uni.fmi.mjt.crowdpulse.config.DatabaseConfig;
import bg.sofia.uni.fmi.mjt.crowdpulse.model.User;
import com.mongodb.client.MongoCollection;
import com.mongodb.client.model.Filters;
import com.mongodb.client.model.Updates;
import org.bson.Document;
import org.bson.conversions.Bson;

import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public class UserRepository {
    private final MongoCollection<Document> collection;

    public UserRepository() {
        this.collection = DatabaseConfig.getDatabase().getCollection("users");
    }

    public User findByUsername(String username) {
        Document doc = collection.find(Filters.eq("username", username)).first();
        return mapToUser(doc);
    }

    public User findById(String id) {
        Document doc = collection.find(Filters.eq("_id", id)).first();
        return mapToUser(doc);
    }

    public void save(User user) {
        if (user.getId() == null) {
            user.setId(UUID.randomUUID().toString());
        }
        Document doc = mapToDocument(user);

        // Upsert logic (replace if exists, insert if not)
        Bson filter = Filters.eq("_id", user.getId());
        if (collection.countDocuments(filter) > 0) {
            collection.replaceOne(filter, doc);
        } else {
            collection.insertOne(doc);
        }
    }

    public void updatePoints(String userId, int points) {
        collection.updateOne(Filters.eq("_id", userId), Updates.set("points", points));
    }

    private User mapToUser(Document doc) {
        if (doc == null)
            return null;
        User user = new User();
        user.setId(doc.getString("_id"));
        user.setUsername(doc.getString("username"));
        user.setPassword(doc.getString("password"));
        user.setRole(doc.getString("role"));
        user.setPoints(doc.getInteger("points", 0));
        user.setGender(doc.getString("gender"));
        user.setCategories(doc.getList("categories", String.class));
        user.setCreatedAt(doc.getLong("created_at") != null ? doc.getLong("created_at") : 0);
        return user;
    }

    private Document mapToDocument(User user) {
        Document doc = new Document();
        doc.append("_id", user.getId());
        doc.append("username", user.getUsername());
        doc.append("password", user.getPassword());
        doc.append("role", user.getRole());
        doc.append("points", user.getPoints());
        doc.append("gender", user.getGender());
        doc.append("categories", user.getCategories());
        doc.append("created_at", user.getCreatedAt());
        return doc;
    }
}
